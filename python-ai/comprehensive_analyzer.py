"""
Comprehensive Image Analyzer - Multi-Pass VLM Analysis System.

This module provides the ComprehensiveImageAnalyzer class which performs
4-pass analysis on images for maximum insight extraction:
- Pass 1: Content & Scene Analysis
- Pass 2: People & Emotion Analysis
- Pass 3: Quality & Technical Analysis
- Pass 4: Context & Metadata Generation

Estimated time: 40-60 seconds per image (quality-first approach).
"""

import logging
import time
import base64
from io import BytesIO
from typing import Dict, List, Optional, Any, Tuple
from dataclasses import dataclass, field
from datetime import datetime
from PIL import Image
import json

from prompts import (
    CONTENT_ANALYSIS_PROMPT,
    PEOPLE_ANALYSIS_PROMPT,
    QUALITY_ANALYSIS_PROMPT,
    CONTEXT_ANALYSIS_PROMPT,
    QUICK_ANALYSIS_PROMPT,
    get_prompt,
    get_config,
    get_comprehensive_passes,
    build_analysis_chain,
    format_prompt_with_context,
    AnalysisPass,
)

logger = logging.getLogger(__name__)


@dataclass
class AnalysisResult:
    """Result from a single analysis pass."""
    pass_type: str
    success: bool
    data: Dict
    duration_seconds: float
    error: Optional[str] = None
    raw_response: Optional[str] = None


@dataclass
class ComprehensiveAnalysisResult:
    """Complete result from comprehensive multi-pass analysis."""
    success: bool
    content: Dict = field(default_factory=dict)
    people: Dict = field(default_factory=dict)
    quality: Dict = field(default_factory=dict)
    context: Dict = field(default_factory=dict)
    combined: Dict = field(default_factory=dict)
    pass_results: List[AnalysisResult] = field(default_factory=list)
    total_duration_seconds: float = 0.0
    passes_completed: int = 0
    passes_failed: int = 0
    errors: List[str] = field(default_factory=list)
    timestamp: str = field(default_factory=lambda: datetime.now().isoformat())

    def to_dict(self) -> Dict:
        """Convert to dictionary for JSON serialization."""
        return {
            "success": self.success,
            "content_analysis": self.content,
            "people_analysis": self.people,
            "quality_analysis": self.quality,
            "context_analysis": self.context,
            "combined_analysis": self.combined,
            "metadata": {
                "total_duration_seconds": self.total_duration_seconds,
                "passes_completed": self.passes_completed,
                "passes_failed": self.passes_failed,
                "errors": self.errors,
                "timestamp": self.timestamp,
            }
        }


class ComprehensiveImageAnalyzer:
    """
    Multi-pass image analyzer using VLM for comprehensive insight extraction.

    Features:
    - 4-pass analysis (content, people, quality, context)
    - Context chaining between passes for coherent results
    - Robust JSON parsing with fallback strategies
    - Quality-first approach with configurable speed/quality tradeoff
    """

    def __init__(
        self,
        ollama_client,
        ollama_model: str = "llava:13b-v1.6",
        quick_mode: bool = False,
        enable_context_chaining: bool = True,
        max_retries: int = 2,
        timeout_seconds: int = 120,
    ):
        """
        Initialize the comprehensive analyzer.

        Args:
            ollama_client: Initialized ollama client instance
            ollama_model: Vision model to use (default: llava:13b-v1.6)
            quick_mode: If True, use single-pass quick analysis (~8s)
            enable_context_chaining: Pass context between analysis passes
            max_retries: Max retries per pass on failure
            timeout_seconds: Timeout for each API call
        """
        self.ollama_client = ollama_client
        self.ollama_model = ollama_model
        self.quick_mode = quick_mode
        self.enable_context_chaining = enable_context_chaining
        self.max_retries = max_retries
        self.timeout_seconds = timeout_seconds

        # Import the JSON extractor from main module
        from main_multimedia import extract_json_from_response
        self.extract_json = extract_json_from_response

    def _image_to_base64(self, image: Image.Image) -> str:
        """Convert PIL Image to base64 string for Ollama."""
        buffered = BytesIO()
        # Use JPEG for efficiency, preserve RGB
        if image.mode in ('RGBA', 'P'):
            image = image.convert('RGB')
        image.save(buffered, format="JPEG", quality=85)
        return base64.b64encode(buffered.getvalue()).decode('utf-8')

    def _run_single_pass(
        self,
        image_base64: str,
        pass_type: str,
        previous_analysis: Optional[Dict] = None,
        image_metadata: Optional[Dict] = None,
    ) -> AnalysisResult:
        """
        Run a single analysis pass.

        Args:
            image_base64: Base64-encoded image
            pass_type: Type of analysis pass (content, people, quality, context)
            previous_analysis: Results from previous passes for context
            image_metadata: Image technical metadata

        Returns:
            AnalysisResult with parsed data or error
        """
        start_time = time.time()
        prompt = get_prompt(pass_type)
        config = get_config(pass_type)

        # Add context from previous passes if enabled
        if self.enable_context_chaining and previous_analysis:
            prompt = format_prompt_with_context(prompt, previous_analysis, image_metadata)

        temperature = config.temperature if config else 0.3
        max_tokens = config.max_tokens if config else 1024

        for attempt in range(self.max_retries + 1):
            try:
                response = self.ollama_client.generate(
                    model=self.ollama_model,
                    prompt=prompt,
                    images=[image_base64],
                    options={
                        "temperature": temperature,
                        "num_predict": max_tokens,
                    }
                )

                raw_response = response.get('response', '')
                parsed_data = self.extract_json(raw_response)

                if parsed_data:
                    duration = time.time() - start_time
                    logger.info(f"Pass '{pass_type}' completed in {duration:.2f}s")
                    return AnalysisResult(
                        pass_type=pass_type,
                        success=True,
                        data=parsed_data,
                        duration_seconds=duration,
                        raw_response=raw_response[:500] if raw_response else None,
                    )
                else:
                    logger.warning(f"Pass '{pass_type}' attempt {attempt+1}: Failed to parse JSON")
                    if attempt < self.max_retries:
                        continue

            except Exception as e:
                logger.error(f"Pass '{pass_type}' attempt {attempt+1} failed: {str(e)}")
                if attempt < self.max_retries:
                    continue

        # All retries exhausted
        duration = time.time() - start_time
        return AnalysisResult(
            pass_type=pass_type,
            success=False,
            data={},
            duration_seconds=duration,
            error=f"Failed after {self.max_retries + 1} attempts",
            raw_response=raw_response[:500] if 'raw_response' in dir() else None,
        )

    def _combine_results(self, results: Dict[str, Dict]) -> Dict:
        """
        Combine results from all passes into a unified structure.

        Args:
            results: Dict mapping pass_type to result data

        Returns:
            Combined analysis dictionary
        """
        combined = {
            "summary": {},
            "tags": [],
            "searchable_keywords": [],
            "suggested_albums": [],
            "quality_tier": "average",
            "has_people": False,
            "people_count": 0,
        }

        # Extract key information from each pass
        content = results.get("content", {})
        people = results.get("people", {})
        quality = results.get("quality", {})
        context = results.get("context", {})

        # Build summary from content analysis
        if content:
            combined["summary"]["main_subjects"] = content.get("main_subjects", [])
            combined["summary"]["setting"] = content.get("setting", {})
            combined["summary"]["description"] = content.get("description", "")
            combined["summary"]["activities"] = content.get("activities", [])

        # People information
        if people:
            combined["has_people"] = people.get("has_people", False)
            combined["people_count"] = people.get("people_count", 0)
            combined["summary"]["group_dynamics"] = people.get("group_dynamics", {})
            combined["summary"]["emotions"] = [
                p.get("emotion", "unknown")
                for p in people.get("people", [])
                if p.get("emotion")
            ]

        # Quality tier from quality analysis
        if quality:
            overall = quality.get("overall_quality", {})
            combined["quality_tier"] = overall.get("tier", "average")
            combined["quality_score"] = overall.get("score", 5.0)
            combined["summary"]["technical_issues"] = quality.get("technical_issues", [])

        # Tags and albums from context analysis
        if context:
            combined["tags"] = context.get("suggested_tags", [])
            combined["searchable_keywords"] = context.get("searchable_keywords", [])
            combined["suggested_albums"] = context.get("suggested_albums", [])
            combined["summary"]["occasion"] = context.get("occasion", {})
            combined["summary"]["temporal"] = context.get("temporal", {})

            # Check for special attributes
            special = context.get("special_attributes", {})
            combined["is_screenshot"] = special.get("is_screenshot", False)
            combined["is_document"] = special.get("is_document", False)
            combined["is_selfie"] = special.get("is_selfie", False)

        return combined

    def analyze(
        self,
        image: Image.Image,
        image_metadata: Optional[Dict] = None,
    ) -> ComprehensiveAnalysisResult:
        """
        Perform comprehensive multi-pass analysis on an image.

        Args:
            image: PIL Image to analyze
            image_metadata: Optional metadata (dimensions, camera info, etc.)

        Returns:
            ComprehensiveAnalysisResult with all analysis data
        """
        start_time = time.time()
        result = ComprehensiveAnalysisResult(success=True)

        try:
            # Convert image to base64 once
            image_base64 = self._image_to_base64(image)

            if self.quick_mode:
                # Quick mode: single pass
                pass_result = self._run_single_pass(
                    image_base64,
                    "quick",
                    image_metadata=image_metadata,
                )
                result.pass_results.append(pass_result)

                if pass_result.success:
                    result.content = pass_result.data
                    result.combined = pass_result.data
                    result.passes_completed = 1
                else:
                    result.passes_failed = 1
                    result.errors.append(pass_result.error or "Quick analysis failed")
            else:
                # Comprehensive mode: 4-pass analysis
                passes = get_comprehensive_passes()  # ["content", "people", "quality", "context"]
                previous_analysis = {}

                for pass_type in passes:
                    pass_result = self._run_single_pass(
                        image_base64,
                        pass_type,
                        previous_analysis=previous_analysis,
                        image_metadata=image_metadata,
                    )
                    result.pass_results.append(pass_result)

                    if pass_result.success:
                        result.passes_completed += 1
                        previous_analysis[pass_type] = pass_result.data

                        # Store in appropriate field
                        if pass_type == "content":
                            result.content = pass_result.data
                        elif pass_type == "people":
                            result.people = pass_result.data
                        elif pass_type == "quality":
                            result.quality = pass_result.data
                        elif pass_type == "context":
                            result.context = pass_result.data
                    else:
                        result.passes_failed += 1
                        if pass_result.error:
                            result.errors.append(f"{pass_type}: {pass_result.error}")

                # Combine all results
                result.combined = self._combine_results(previous_analysis)

        except Exception as e:
            logger.error(f"Comprehensive analysis failed: {str(e)}")
            result.success = False
            result.errors.append(str(e))

        result.total_duration_seconds = time.time() - start_time

        # Consider success if at least content pass completed
        if result.passes_completed == 0:
            result.success = False

        logger.info(
            f"Comprehensive analysis completed: "
            f"{result.passes_completed}/{result.passes_completed + result.passes_failed} passes "
            f"in {result.total_duration_seconds:.2f}s"
        )

        return result

    def analyze_batch(
        self,
        images: List[Tuple[Image.Image, Optional[Dict]]],
        max_concurrent: int = 1,
    ) -> List[ComprehensiveAnalysisResult]:
        """
        Analyze multiple images.

        Args:
            images: List of (image, metadata) tuples
            max_concurrent: Max concurrent analyses (default 1 for memory)

        Returns:
            List of ComprehensiveAnalysisResult
        """
        results = []
        for i, (image, metadata) in enumerate(images):
            logger.info(f"Analyzing image {i+1}/{len(images)}")
            result = self.analyze(image, metadata)
            results.append(result)
        return results


def create_analyzer(
    ollama_client,
    ollama_model: str = "llava:13b-v1.6",
    quick_mode: bool = False,
) -> ComprehensiveImageAnalyzer:
    """
    Factory function to create a ComprehensiveImageAnalyzer.

    Args:
        ollama_client: Initialized ollama client
        ollama_model: Vision model name
        quick_mode: Use quick single-pass mode

    Returns:
        Configured ComprehensiveImageAnalyzer instance
    """
    return ComprehensiveImageAnalyzer(
        ollama_client=ollama_client,
        ollama_model=ollama_model,
        quick_mode=quick_mode,
    )
