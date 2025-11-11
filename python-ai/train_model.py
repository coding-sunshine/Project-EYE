"""
Train and fine-tune AI models using existing images with Ollama.

This script learns from your image collection to:
1. Improve search relevance
2. Better categorization
3. Personalized descriptions
4. Enhanced face recognition

Uses Ollama for fast, local AI analysis without downloading large models.
"""

import os
import json
import logging
import sys
import base64
from pathlib import Path
from typing import List, Dict, Tuple
import numpy as np
import pickle
from datetime import datetime
from collections import Counter, defaultdict

# Try to import ollama
try:
    import ollama
    OLLAMA_AVAILABLE = True
except ImportError:
    OLLAMA_AVAILABLE = False
    logging.error("Ollama not installed! Please install: pip install ollama")
    sys.exit(1)

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# Paths
SHARED_PATH = Path("/app/shared")
TRAINING_DATA_PATH = Path("/app/training_data")
MODELS_PATH = Path("/app/models")
TRAINING_DATA_PATH.mkdir(exist_ok=True)
MODELS_PATH.mkdir(exist_ok=True)

# Ollama configuration
OLLAMA_HOST = os.getenv('OLLAMA_HOST', 'http://ollama:11434')
OLLAMA_MODEL = os.getenv('OLLAMA_MODEL', 'llava')


class ImageTrainer:
    """Train and improve models based on existing images using Ollama."""
    
    def __init__(self):
        logger.info("Initializing trainer with Ollama...")
        
        # Initialize Ollama client
        try:
            self.ollama_client = ollama.Client(host=OLLAMA_HOST)
            logger.info(f"Connected to Ollama at {OLLAMA_HOST}")
            
            # Check if model is available
            try:
                models = self.ollama_client.list()
                model_names = [m['name'] for m in models.get('models', [])]
                if OLLAMA_MODEL not in model_names:
                    logger.warning(f"Model '{OLLAMA_MODEL}' not found. Available models: {model_names}")
                    logger.info(f"Please pull the model: docker compose exec ollama ollama pull {OLLAMA_MODEL}")
                else:
                    logger.info(f"✅ Using Ollama model: {OLLAMA_MODEL}")
            except Exception as e:
                logger.warning(f"Could not check Ollama models: {e}")
                
        except Exception as e:
            logger.error(f"Failed to connect to Ollama: {e}")
            raise
        
        # Training data
        self.image_data = []
        self.category_patterns = defaultdict(list)
        self.description_patterns = {}
        self.face_clusters = {}
        
    def check_ollama_connection(self):
        """Verify Ollama is accessible."""
        try:
            self.ollama_client.list()
            logger.info("✅ Ollama connection verified")
            return True
        except Exception as e:
            logger.error(f"❌ Ollama connection failed: {e}")
            return False
    
    def analyze_image_with_ollama(self, image_path: str) -> Dict:
        """
        Analyze an image using Ollama vision model.
        
        Args:
            image_path: Path to the image file
            
        Returns:
            Dict with description, detailed_description, and meta_tags
        """
        try:
            # Read and encode image
            with open(image_path, "rb") as img_file:
                img_data = base64.b64encode(img_file.read()).decode('utf-8')
            
            prompt = """Analyze this image in detail and provide:
1. A brief caption (one sentence)
2. A very detailed description (3-4 sentences) including colors, objects, people, setting, mood, and any notable details.
3. A list of meta tags/keywords (comma-separated) for searching, including: main subjects, colors, objects, setting, style.

Format your response as JSON:
{
  "caption": "...",
  "detailed_description": "...",
  "meta_tags": ["tag1", "tag2", ...]
}"""

            # Call Ollama
            response = self.ollama_client.generate(
                model=OLLAMA_MODEL,
                prompt=prompt,
                images=[img_data],
                stream=False
            )
            
            result_text = response.get('response', '')
            
            # Try to parse JSON from response
            try:
                # Extract JSON from response (might have markdown code blocks)
                if '```json' in result_text:
                    json_start = result_text.find('```json') + 7
                    json_end = result_text.find('```', json_start)
                    result_text = result_text[json_start:json_end].strip()
                elif '```' in result_text:
                    json_start = result_text.find('```') + 3
                    json_end = result_text.find('```', json_start)
                    result_text = result_text[json_start:json_end].strip()
                
                result = json.loads(result_text)
                return {
                    'description': result.get('caption', ''),
                    'detailed_description': result.get('detailed_description', ''),
                    'meta_tags': result.get('meta_tags', [])
                }
            except json.JSONDecodeError:
                # Fallback: extract information from text
                logger.warning(f"Could not parse JSON from Ollama response, using text extraction")
                lines = result_text.split('\n')
                description = lines[0] if lines else result_text[:200]
                return {
                    'description': description,
                    'detailed_description': result_text[:500],
                    'meta_tags': self._extract_tags_from_text(result_text)
                }
                
        except Exception as e:
            logger.error(f"Failed to analyze image {image_path} with Ollama: {e}")
            return {
                'description': '',
                'detailed_description': '',
                'meta_tags': []
            }
    
    def _extract_tags_from_text(self, text: str) -> List[str]:
        """Extract potential tags from text."""
        # Simple keyword extraction
        words = text.lower().split()
        # Filter common words and keep meaningful ones
        stop_words = {'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'is', 'are', 'was', 'were'}
        tags = [w for w in words if len(w) > 3 and w not in stop_words]
        return list(set(tags))[:10]  # Return up to 10 unique tags
    
    def collect_training_data(self, metadata_file: str):
        """
        Collect training data from existing images.
        
        Args:
            metadata_file: Path to JSON file with image metadata
        """
        logger.info(f"Collecting training data from: {metadata_file}")
        
        try:
            with open(metadata_file, 'r') as f:
                data = json.load(f)
            
            for item in data:
                image_path = SHARED_PATH / item['filename']
                if not image_path.exists():
                    continue
                
                self.image_data.append({
                    'path': str(image_path),
                    'description': item.get('description', ''),
                    'detailed_description': item.get('detailed_description', ''),
                    'meta_tags': item.get('meta_tags', []),
                    'face_count': item.get('face_count', 0),
                    'embedding': item.get('embedding', None)
                })
            
            logger.info(f"Collected {len(self.image_data)} images for training")
        except Exception as e:
            logger.error(f"Failed to collect training data: {e}")
    
    def analyze_category_patterns(self):
        """
        Analyze patterns in image categories and tags.
        This helps improve auto-categorization.
        """
        logger.info("Analyzing category patterns...")
        
        # Collect all tags
        all_tags = []
        for item in self.image_data:
            all_tags.extend(item['meta_tags'])
        
        # Find most common tags
        tag_counts = Counter(all_tags)
        common_tags = tag_counts.most_common(50)
        
        logger.info(f"Found {len(tag_counts)} unique tags")
        logger.info(f"Top 10 tags: {common_tags[:10]}")
        
        # Analyze co-occurrence patterns
        for item in self.image_data:
            tags = item['meta_tags']
            for tag in tags:
                # Store images that have this tag
                self.category_patterns[tag].append({
                    'description': item['description'],
                    'other_tags': [t for t in tags if t != tag]
                })
        
        # Save patterns
        patterns_file = TRAINING_DATA_PATH / 'category_patterns.json'
        with open(patterns_file, 'w') as f:
            json.dump({
                'common_tags': common_tags,
                'tag_counts': dict(tag_counts),
                'patterns': {k: v[:100] for k, v in self.category_patterns.items()}  # Limit size
            }, f, indent=2)
        
        logger.info(f"Category patterns saved to: {patterns_file}")
    
    def analyze_description_patterns(self):
        """
        Analyze patterns in descriptions to improve generation.
        """
        logger.info("Analyzing description patterns...")
        
        # Group descriptions by tags
        for item in self.image_data:
            for tag in item['meta_tags']:
                if tag not in self.description_patterns:
                    self.description_patterns[tag] = []
                
                if item['detailed_description']:
                    self.description_patterns[tag].append(item['detailed_description'])
        
        # Analyze patterns
        pattern_analysis = {}
        for tag, descriptions in self.description_patterns.items():
            if len(descriptions) < 3:
                continue
            
            # Extract common phrases
            all_words = []
            for desc in descriptions:
                all_words.extend(desc.lower().split())
            
            word_counts = Counter(all_words)
            common_words = [w for w, c in word_counts.most_common(20) if len(w) > 3]
            
            pattern_analysis[tag] = {
                'count': len(descriptions),
                'common_words': common_words,
                'avg_length': sum(len(d.split()) for d in descriptions) / len(descriptions)
            }
        
        # Save patterns
        patterns_file = TRAINING_DATA_PATH / 'description_patterns.json'
        with open(patterns_file, 'w') as f:
            json.dump(pattern_analysis, f, indent=2)
        
        logger.info(f"Description patterns saved to: {patterns_file}")
    
    def extract_face_features(self):
        """
        Extract and cluster face features for better face recognition.
        Requires face_recognition library.
        """
        logger.info("Extracting face features...")
        
        try:
            import face_recognition
        except ImportError:
            logger.warning("face_recognition not available, skipping face clustering")
            return
        
        face_encodings_all = []
        face_metadata = []
        
        for item in self.image_data:
            if item['face_count'] == 0:
                continue
            
            try:
                image = face_recognition.load_image_file(item['path'])
                encodings = face_recognition.face_encodings(image)
                
                for encoding in encodings:
                    face_encodings_all.append(encoding)
                    face_metadata.append({
                        'image': item['path'],
                        'tags': item['meta_tags']
                    })
            except Exception as e:
                logger.warning(f"Failed to process faces in {item['path']}: {e}")
        
        if not face_encodings_all:
            logger.info("No faces found for clustering")
            return
        
        logger.info(f"Extracted {len(face_encodings_all)} face encodings")
        
        # Simple clustering based on similarity
        clusters = []
        used = set()
        
        for i, encoding in enumerate(face_encodings_all):
            if i in used:
                continue
            
            cluster = [i]
            used.add(i)
            
            for j, other_encoding in enumerate(face_encodings_all):
                if j in used or i == j:
                    continue
                
                distance = face_recognition.face_distance([encoding], other_encoding)[0]
                if distance < 0.6:  # Threshold for same person
                    cluster.append(j)
                    used.add(j)
            
            if len(cluster) >= 2:  # Only clusters with multiple faces
                clusters.append(cluster)
        
        logger.info(f"Found {len(clusters)} face clusters")
        
        # Save clusters
        clusters_file = TRAINING_DATA_PATH / 'face_clusters.pkl'
        with open(clusters_file, 'wb') as f:
            pickle.dump({
                'clusters': clusters,
                'metadata': face_metadata,
                'encodings': face_encodings_all
            }, f)
        
        logger.info(f"Face clusters saved to: {clusters_file}")
    
    def generate_improved_descriptions(self, output_file: str):
        """
        Generate improved descriptions using Ollama and learned patterns.
        
        Args:
            output_file: Path to save improved descriptions
        """
        logger.info("Generating improved descriptions with Ollama...")
        logger.info(f"Processing {len(self.image_data)} images...")
        
        improvements = []
        
        for idx, item in enumerate(self.image_data, 1):
            try:
                logger.info(f"Processing image {idx}/{len(self.image_data)}: {item['path']}")
                
                # Analyze with Ollama
                ollama_result = self.analyze_image_with_ollama(item['path'])
                
                # Enhance based on learned patterns
                relevant_tags = item['meta_tags']
                enhancement_context = []
                
                for tag in relevant_tags:
                    if tag in self.description_patterns:
                        patterns = self.description_patterns.get(tag, [])
                        if patterns:
                            # Use patterns to enhance description
                            enhancement_context.append(f"Common {tag} descriptions include: {', '.join(patterns[:3])}")
                
                improvements.append({
                    'image': item['path'],
                    'original_description': item['description'],
                    'ollama_description': ollama_result.get('description', ''),
                    'ollama_detailed': ollama_result.get('detailed_description', ''),
                    'ollama_tags': ollama_result.get('meta_tags', []),
                    'suggested_tags': relevant_tags,
                    'enhancement_context': enhancement_context
                })
                
                # Small delay to avoid overwhelming Ollama
                if idx % 10 == 0:
                    logger.info(f"Progress: {idx}/{len(self.image_data)} images processed")
                
            except Exception as e:
                logger.warning(f"Failed to improve description for {item['path']}: {e}")
        
        # Save improvements
        with open(output_file, 'w') as f:
            json.dump(improvements, f, indent=2)
        
        logger.info(f"Generated {len(improvements)} improved descriptions")
        logger.info(f"Saved to: {output_file}")
    
    def build_search_index(self):
        """
        Build improved search index using learned patterns.
        """
        logger.info("Building improved search index...")
        
        # Create synonym mappings based on co-occurrence
        synonyms = defaultdict(set)
        
        for tag, data in self.category_patterns.items():
            # Find tags that often appear together
            co_occurring = []
            for item in data:
                co_occurring.extend(item['other_tags'])
            
            if co_occurring:
                common_co = Counter(co_occurring).most_common(5)
                for related_tag, count in common_co:
                    if count >= 3:  # Appears together at least 3 times
                        synonyms[tag].add(related_tag)
                        synonyms[related_tag].add(tag)
        
        # Save search improvements
        search_index = {
            'synonyms': {k: list(v) for k, v in synonyms.items()},
            'timestamp': datetime.now().isoformat()
        }
        
        index_file = TRAINING_DATA_PATH / 'search_index.json'
        with open(index_file, 'w') as f:
            json.dump(search_index, f, indent=2)
        
        logger.info(f"Search index saved to: {index_file}")
        logger.info(f"Found {len(synonyms)} terms with related concepts")
    
    def generate_training_report(self):
        """Generate a summary report of the training process."""
        logger.info("Generating training report...")
        
        report = {
            'timestamp': datetime.now().isoformat(),
            'ollama_model': OLLAMA_MODEL,
            'ollama_host': OLLAMA_HOST,
            'total_images': len(self.image_data),
            'total_tags': len(self.category_patterns),
            'images_with_faces': sum(1 for item in self.image_data if item['face_count'] > 0),
            'images_with_detailed_desc': sum(1 for item in self.image_data if item['detailed_description']),
            'top_categories': list(self.category_patterns.keys())[:20],
            'status': 'completed'
        }
        
        report_file = TRAINING_DATA_PATH / 'training_report.json'
        with open(report_file, 'w') as f:
            json.dump(report, f, indent=2)
        
        logger.info("Training report:")
        for key, value in report.items():
            logger.info(f"  {key}: {value}")
        
        return report


def main():
    """Main training workflow."""
    logger.info("=" * 60)
    logger.info("Starting AI Model Training with Ollama")
    logger.info("=" * 60)
    
    # Initialize trainer
    trainer = ImageTrainer()
    
    # Verify Ollama connection
    if not trainer.check_ollama_connection():
        logger.error("Cannot proceed without Ollama connection")
        return
    
    # Check for metadata file
    metadata_file = TRAINING_DATA_PATH / 'images_metadata.json'
    if not metadata_file.exists():
        logger.error(f"Metadata file not found: {metadata_file}")
        logger.info("Please export training data first using: php artisan export:training-data")
        return
    
    # Collect training data
    trainer.collect_training_data(str(metadata_file))
    
    if not trainer.image_data:
        logger.warning("No training data found!")
        return
    
    # Analyze patterns
    logger.info("\n" + "=" * 60)
    logger.info("Phase 1: Analyzing Category Patterns")
    logger.info("=" * 60)
    trainer.analyze_category_patterns()
    
    logger.info("\n" + "=" * 60)
    logger.info("Phase 2: Analyzing Description Patterns")
    logger.info("=" * 60)
    trainer.analyze_description_patterns()
    
    logger.info("\n" + "=" * 60)
    logger.info("Phase 3: Extracting Face Features")
    logger.info("=" * 60)
    trainer.extract_face_features()
    
    logger.info("\n" + "=" * 60)
    logger.info("Phase 4: Building Search Index")
    logger.info("=" * 60)
    trainer.build_search_index()
    
    logger.info("\n" + "=" * 60)
    logger.info("Phase 5: Generating Improved Descriptions with Ollama")
    logger.info("=" * 60)
    improvements_file = TRAINING_DATA_PATH / 'improved_descriptions.json'
    trainer.generate_improved_descriptions(str(improvements_file))
    
    # Generate report
    logger.info("\n" + "=" * 60)
    logger.info("Generating Training Report")
    logger.info("=" * 60)
    report = trainer.generate_training_report()
    
    logger.info("\n" + "=" * 60)
    logger.info("Training Complete!")
    logger.info("=" * 60)
    logger.info(f"Training data saved in: {TRAINING_DATA_PATH}")
    logger.info("\nFiles created:")
    logger.info(f"  - category_patterns.json")
    logger.info(f"  - description_patterns.json")
    logger.info(f"  - face_clusters.pkl")
    logger.info(f"  - search_index.json")
    logger.info(f"  - improved_descriptions.json")
    logger.info(f"  - training_report.json")


if __name__ == "__main__":
    main()
