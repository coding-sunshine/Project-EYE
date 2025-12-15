"""
VLM Prompt Templates for Comprehensive Multi-Pass Image Analysis.

This module provides structured prompts for the 4-pass analysis system:
- Pass 1: Content & Scene Analysis
- Pass 2: People & Emotion Analysis
- Pass 3: Quality & Technical Analysis
- Pass 4: Context & Metadata Generation

Each prompt is optimized for LLaVA 1.6 13B and returns structured JSON.
"""

from typing import Dict, List, Optional
from dataclasses import dataclass
from enum import Enum


class AnalysisPass(Enum):
    """Enumeration of analysis passes."""
    CONTENT = "content"
    PEOPLE = "people"
    QUALITY = "quality"
    CONTEXT = "context"


@dataclass
class PromptConfig:
    """Configuration for a prompt template."""
    name: str
    pass_type: AnalysisPass
    temperature: float
    max_tokens: int
    description: str


# ============================================================================
# PASS 1: CONTENT & SCENE ANALYSIS (~12s)
# ============================================================================

CONTENT_ANALYSIS_PROMPT = """Analyze this image comprehensively. Focus on WHAT is in the image.

Provide a detailed JSON response with the following structure:
{
    "main_subjects": ["list of primary subjects/focal points"],
    "objects": ["list of all identifiable objects"],
    "setting": {
        "type": "indoor/outdoor/mixed",
        "location_type": "home/office/street/nature/restaurant/store/etc",
        "specific_location": "more specific description if identifiable"
    },
    "environment": {
        "atmosphere": "calm/busy/intimate/festive/professional/etc",
        "lighting_conditions": "natural/artificial/mixed/dim/bright",
        "weather": "sunny/cloudy/rainy/snowy/not applicable"
    },
    "activities": ["list of actions/activities happening"],
    "composition": {
        "style": "portrait/landscape/close-up/wide-shot/aerial/macro",
        "framing": "centered/rule-of-thirds/asymmetric/etc",
        "depth": "shallow/deep/flat",
        "dominant_colors": ["primary colors in the image"]
    },
    "description": "2-3 sentence natural language description of the entire scene"
}

Be specific and detailed. List all visible objects. Describe the scene thoroughly.
Respond ONLY with valid JSON, no additional text."""

CONTENT_ANALYSIS_CONFIG = PromptConfig(
    name="Content Analysis",
    pass_type=AnalysisPass.CONTENT,
    temperature=0.3,
    max_tokens=1024,
    description="Analyzes main subjects, objects, setting, and composition"
)


# ============================================================================
# PASS 2: PEOPLE & EMOTION ANALYSIS (~10s)
# ============================================================================

PEOPLE_ANALYSIS_PROMPT = """Analyze the PEOPLE in this image. If no people are visible, respond with the empty structure.

Provide a detailed JSON response with the following structure:
{
    "people_count": 0,
    "has_people": false,
    "people": [
        {
            "position": "left/center/right/background",
            "estimated_age_range": "child/teen/young-adult/middle-aged/senior",
            "estimated_gender": "male/female/uncertain",
            "emotion": "happy/sad/neutral/surprised/angry/contemplative/etc",
            "emotion_confidence": "high/medium/low",
            "expression_details": "description of facial expression",
            "pose": "standing/sitting/walking/lying/other",
            "attire": "casual/formal/athletic/uniform/etc",
            "notable_features": ["glasses", "hat", "beard", etc]
        }
    ],
    "group_dynamics": {
        "interaction_type": "conversation/posing/activity/none",
        "relationship_inference": "family/friends/colleagues/strangers/romantic/etc",
        "engagement_level": "high/medium/low/none"
    },
    "body_language": {
        "overall_mood": "positive/negative/neutral",
        "openness": "open/closed/mixed",
        "energy_level": "high/medium/low"
    },
    "faces_visible": true,
    "faces_clear": true
}

If no people are present, set people_count to 0, has_people to false, and leave people array empty.
Be objective and respectful in descriptions. Avoid assumptions about identity.
Respond ONLY with valid JSON, no additional text."""

PEOPLE_ANALYSIS_CONFIG = PromptConfig(
    name="People Analysis",
    pass_type=AnalysisPass.PEOPLE,
    temperature=0.3,
    max_tokens=1024,
    description="Analyzes people, emotions, demographics, and interactions"
)


# ============================================================================
# PASS 3: QUALITY & TECHNICAL ANALYSIS (~8s)
# ============================================================================

QUALITY_ANALYSIS_PROMPT = """Evaluate the TECHNICAL QUALITY of this image as a photography expert.

Provide a detailed JSON response with the following structure:
{
    "overall_quality": {
        "score": 7.5,
        "tier": "excellent/good/average/poor",
        "summary": "brief quality assessment"
    },
    "focus": {
        "sharpness": "sharp/slightly-soft/soft/blurry",
        "focus_area": "subject/background/everything/nothing",
        "depth_of_field": "shallow/medium/deep",
        "motion_blur": "none/slight/moderate/severe"
    },
    "exposure": {
        "level": "well-exposed/underexposed/overexposed",
        "dynamic_range": "good/limited/clipped",
        "highlights": "preserved/blown",
        "shadows": "detailed/crushed"
    },
    "lighting": {
        "quality": "excellent/good/fair/poor",
        "type": "natural/artificial/mixed/flash",
        "direction": "front/side/back/diffused",
        "harshness": "soft/medium/harsh"
    },
    "color": {
        "accuracy": "accurate/warm/cool/oversaturated/muted",
        "white_balance": "correct/warm/cool/mixed",
        "saturation": "natural/boosted/muted",
        "contrast": "good/low/high"
    },
    "composition": {
        "balance": "balanced/unbalanced",
        "subject_placement": "good/awkward",
        "distractions": "none/minor/major",
        "cropping": "appropriate/too-tight/too-loose"
    },
    "technical_issues": ["list any issues: noise, artifacts, chromatic aberration, etc"],
    "improvements_suggested": ["list of potential improvements"]
}

Score from 1-10 where 10 is professional quality. Be critical but fair.
Respond ONLY with valid JSON, no additional text."""

QUALITY_ANALYSIS_CONFIG = PromptConfig(
    name="Quality Analysis",
    pass_type=AnalysisPass.QUALITY,
    temperature=0.2,
    max_tokens=1024,
    description="Evaluates technical quality, focus, exposure, and composition"
)


# ============================================================================
# PASS 4: CONTEXT & METADATA GENERATION (~10s)
# ============================================================================

CONTEXT_ANALYSIS_PROMPT = """Analyze the CONTEXT and generate METADATA for this image.

Provide a detailed JSON response with the following structure:
{
    "temporal": {
        "time_of_day": "morning/afternoon/evening/night/unclear",
        "season": "spring/summer/fall/winter/unclear",
        "era": "contemporary/vintage/historical/unclear",
        "event_timing": "during-event/posed/candid/unclear"
    },
    "occasion": {
        "event_type": "wedding/birthday/holiday/vacation/work/everyday/etc",
        "formality": "formal/casual/semi-formal",
        "significance": "special-occasion/routine/milestone"
    },
    "brands_products": {
        "visible_brands": ["list of identifiable brand names/logos"],
        "products": ["list of identifiable products"],
        "text_visible": ["any readable text in the image"]
    },
    "location_inference": {
        "geographic_region": "inferred region if identifiable",
        "venue_type": "restaurant/park/home/office/etc",
        "landmarks": ["any identifiable landmarks"],
        "cultural_indicators": ["cultural elements visible"]
    },
    "suggested_tags": ["tag1", "tag2", "tag3", "etc - suggest 10-15 relevant tags"],
    "suggested_albums": ["album suggestions based on content"],
    "title_suggestions": ["2-3 potential titles for this image"],
    "searchable_keywords": ["comprehensive list of search keywords"],
    "content_warnings": {
        "has_sensitive_content": false,
        "warnings": []
    },
    "special_attributes": {
        "is_screenshot": false,
        "is_document": false,
        "is_meme": false,
        "is_artwork": false,
        "is_selfie": false,
        "has_text_overlay": false
    }
}

Be comprehensive with tags and keywords - these help with search.
Suggest albums that would logically contain this image.
Respond ONLY with valid JSON, no additional text."""

CONTEXT_ANALYSIS_CONFIG = PromptConfig(
    name="Context Analysis",
    pass_type=AnalysisPass.CONTEXT,
    temperature=0.3,
    max_tokens=1024,
    description="Infers context, generates tags, and suggests organization"
)


# ============================================================================
# QUICK ANALYSIS (Single-Pass Alternative)
# ============================================================================

QUICK_ANALYSIS_PROMPT = """Quickly analyze this image and provide key information.

Provide a JSON response with:
{
    "description": "1-2 sentence description",
    "main_subjects": ["list of main subjects"],
    "setting": "brief setting description",
    "people_count": 0,
    "mood": "overall mood/atmosphere",
    "quality_score": 7.5,
    "suggested_tags": ["5-7 relevant tags"]
}

Respond ONLY with valid JSON, no additional text."""

QUICK_ANALYSIS_CONFIG = PromptConfig(
    name="Quick Analysis",
    pass_type=AnalysisPass.CONTENT,  # Primary is content
    temperature=0.3,
    max_tokens=512,
    description="Fast single-pass analysis for quick processing"
)


# ============================================================================
# SCENE CLASSIFICATION (Existing - for backward compatibility)
# ============================================================================

SCENE_CLASSIFICATION_PROMPT = """Analyze this image and classify the scene.

Respond with JSON only:
{
    "setting": "indoor/outdoor/vehicle/virtual",
    "environment": "home/office/restaurant/park/street/beach/forest/mountain/etc",
    "mood": "happy/calm/energetic/romantic/professional/festive/dramatic/peaceful",
    "time_of_day": "morning/afternoon/evening/night/unknown",
    "weather": "sunny/cloudy/rainy/snowy/foggy/unknown",
    "season": "spring/summer/fall/winter/unknown"
}

Be specific and accurate. Use only the provided categories.
Respond ONLY with valid JSON, no additional text."""


# ============================================================================
# PROMPT REGISTRY
# ============================================================================

PROMPTS: Dict[str, tuple] = {
    "content": (CONTENT_ANALYSIS_PROMPT, CONTENT_ANALYSIS_CONFIG),
    "people": (PEOPLE_ANALYSIS_PROMPT, PEOPLE_ANALYSIS_CONFIG),
    "quality": (QUALITY_ANALYSIS_PROMPT, QUALITY_ANALYSIS_CONFIG),
    "context": (CONTEXT_ANALYSIS_PROMPT, CONTEXT_ANALYSIS_CONFIG),
    "quick": (QUICK_ANALYSIS_PROMPT, QUICK_ANALYSIS_CONFIG),
    "scene": (SCENE_CLASSIFICATION_PROMPT, None),  # Legacy
}


def get_prompt(pass_type: str) -> str:
    """Get the prompt template for a specific analysis pass."""
    if pass_type in PROMPTS:
        return PROMPTS[pass_type][0]
    raise ValueError(f"Unknown pass type: {pass_type}")


def get_config(pass_type: str) -> Optional[PromptConfig]:
    """Get the configuration for a specific analysis pass."""
    if pass_type in PROMPTS:
        return PROMPTS[pass_type][1]
    return None


def get_all_pass_types() -> List[str]:
    """Get list of all available analysis pass types."""
    return list(PROMPTS.keys())


def get_comprehensive_passes() -> List[str]:
    """Get the list of passes for comprehensive 4-pass analysis."""
    return ["content", "people", "quality", "context"]


# ============================================================================
# PROMPT FORMATTING UTILITIES
# ============================================================================

def format_prompt_with_context(
    prompt: str,
    previous_analysis: Optional[Dict] = None,
    image_metadata: Optional[Dict] = None
) -> str:
    """
    Format a prompt with optional context from previous analysis passes.

    This allows later passes to use information from earlier passes
    for more coherent and consistent analysis.
    """
    context_additions = []

    if previous_analysis:
        # Add relevant context from previous passes
        if "content" in previous_analysis:
            content = previous_analysis["content"]
            if "main_subjects" in content:
                subjects = ", ".join(content.get("main_subjects", []))
                context_additions.append(f"Main subjects identified: {subjects}")
            if "setting" in content:
                setting = content.get("setting", {})
                context_additions.append(f"Setting: {setting.get('type', 'unknown')} - {setting.get('location_type', 'unknown')}")

    if image_metadata:
        # Add technical metadata if available
        if "width" in image_metadata and "height" in image_metadata:
            context_additions.append(f"Image dimensions: {image_metadata['width']}x{image_metadata['height']}")
        if "camera_model" in image_metadata and image_metadata["camera_model"]:
            context_additions.append(f"Camera: {image_metadata['camera_model']}")

    if context_additions:
        context_block = "\n\nContext from previous analysis:\n" + "\n".join(f"- {c}" for c in context_additions)
        return prompt + context_block

    return prompt


def build_analysis_chain() -> List[Dict]:
    """
    Build the ordered chain of analysis passes with dependencies.

    Returns a list of pass configurations in execution order.
    """
    return [
        {
            "pass_type": "content",
            "depends_on": [],
            "prompt": CONTENT_ANALYSIS_PROMPT,
            "config": CONTENT_ANALYSIS_CONFIG,
        },
        {
            "pass_type": "people",
            "depends_on": ["content"],  # Can use scene context
            "prompt": PEOPLE_ANALYSIS_PROMPT,
            "config": PEOPLE_ANALYSIS_CONFIG,
        },
        {
            "pass_type": "quality",
            "depends_on": ["content"],  # Can use composition info
            "prompt": QUALITY_ANALYSIS_PROMPT,
            "config": QUALITY_ANALYSIS_CONFIG,
        },
        {
            "pass_type": "context",
            "depends_on": ["content", "people"],  # Uses both for inference
            "prompt": CONTEXT_ANALYSIS_PROMPT,
            "config": CONTEXT_ANALYSIS_CONFIG,
        },
    ]
