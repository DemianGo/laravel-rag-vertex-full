"""
Issue classification and severity assessment for document extraction failures.
"""

from typing import Dict, Any, List, Optional, Tuple
from enum import Enum
from dataclasses import dataclass
import re


class IssueCategory(Enum):
    """Main categories of extraction issues."""
    TECHNICAL = "technical"
    CONTENT = "content"
    STRUCTURAL = "structural"
    QUALITY = "quality"


class FixComplexity(Enum):
    """Complexity levels for fixing issues."""
    TRIVIAL = "trivial"
    LOW = "low"
    MEDIUM = "medium"
    HIGH = "high"
    EXPERT = "expert"


@dataclass
class IssueClassification:
    """Classification result for an issue."""
    category: IssueCategory
    subcategory: str
    severity: str
    impact_score: float
    fix_complexity: FixComplexity
    fix_probability: float
    dependencies: List[str]
    tools_required: List[str]


class IssueClassifier:
    """Classify and assess extraction issues for prioritization and remediation."""

    def __init__(self):
        self.classification_rules = self._load_classification_rules()
        self.impact_weights = self._load_impact_weights()
        self.fix_strategies = self._load_fix_strategies()

    def classify_issue_type(self, failure_data: Dict[str, Any]) -> IssueClassification:
        """
        Classify an issue based on failure data.

        Args:
            failure_data: Failure information from detector

        Returns:
            Detailed classification of the issue
        """
        issue_type = failure_data.get('type', 'unknown')
        severity = failure_data.get('severity', 'medium')
        pattern = failure_data.get('pattern', '')
        confidence = failure_data.get('confidence', 0.5)

        # Get base classification
        base_classification = self.classification_rules.get(issue_type, {})

        # Calculate impact score
        impact_score = self._calculate_impact_score(failure_data)

        # Determine fix complexity
        fix_complexity = self._assess_fix_complexity(issue_type, pattern, failure_data)

        # Estimate fix probability
        fix_probability = self._estimate_fix_probability(issue_type, pattern, confidence)

        # Identify dependencies and required tools
        dependencies = self._identify_dependencies(issue_type, failure_data)
        tools_required = self._identify_required_tools(issue_type, pattern)

        return IssueClassification(
            category=IssueCategory(base_classification.get('category', 'technical')),
            subcategory=base_classification.get('subcategory', 'unknown'),
            severity=severity,
            impact_score=impact_score,
            fix_complexity=fix_complexity,
            fix_probability=fix_probability,
            dependencies=dependencies,
            tools_required=tools_required
        )

    def assign_severity(self, issue_type: str, impact_metrics: Dict[str, Any]) -> str:
        """
        Assign severity level based on issue type and impact metrics.

        Args:
            issue_type: Type of the issue
            impact_metrics: Metrics about the impact

        Returns:
            Severity level (critical, high, medium, low)
        """
        base_severity = self._get_base_severity(issue_type)

        # Adjust severity based on impact metrics
        impact_factors = {
            'affected_content_ratio': impact_metrics.get('affected_ratio', 0),
            'content_importance': impact_metrics.get('importance_score', 0.5),
            'user_visibility': impact_metrics.get('visibility_score', 0.5),
            'fix_urgency': impact_metrics.get('urgency_score', 0.5)
        }

        severity_adjustment = self._calculate_severity_adjustment(impact_factors)
        final_severity = self._apply_severity_adjustment(base_severity, severity_adjustment)

        return final_severity

    def estimate_fix_effort(self, issue_classification: IssueClassification) -> Dict[str, Any]:
        """
        Estimate effort required to fix an issue.

        Args:
            issue_classification: Classification of the issue

        Returns:
            Effort estimation details
        """
        base_effort = self._get_base_effort(issue_classification.fix_complexity)

        # Adjust effort based on various factors
        effort_multipliers = {
            'severity': self._get_severity_multiplier(issue_classification.severity),
            'dependencies': self._get_dependency_multiplier(len(issue_classification.dependencies)),
            'tools': self._get_tools_multiplier(len(issue_classification.tools_required)),
            'probability': self._get_probability_multiplier(issue_classification.fix_probability)
        }

        total_multiplier = 1.0
        for factor, multiplier in effort_multipliers.items():
            total_multiplier *= multiplier

        estimated_hours = base_effort * total_multiplier

        return {
            'estimated_hours': round(estimated_hours, 1),
            'complexity': issue_classification.fix_complexity.value,
            'confidence': issue_classification.fix_probability,
            'risk_factors': self._identify_risk_factors(issue_classification),
            'prerequisites': issue_classification.dependencies,
            'recommended_tools': issue_classification.tools_required
        }

    def prioritize_issues(self, classified_issues: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """
        Prioritize issues based on severity, impact, and fix probability.

        Args:
            classified_issues: List of classified issues

        Returns:
            Prioritized list of issues
        """
        def priority_score(issue):
            classification = issue.get('classification')
            if not classification:
                return 0

            # Base priority from severity
            severity_scores = {'critical': 100, 'high': 75, 'medium': 50, 'low': 25}
            base_score = severity_scores.get(classification.severity.lower(), 25)

            # Impact multiplier
            impact_multiplier = classification.impact_score

            # Fix probability bonus
            probability_bonus = classification.fix_probability * 20

            # Complexity penalty
            complexity_penalties = {
                'trivial': 0, 'low': -5, 'medium': -10, 'high': -20, 'expert': -30
            }
            complexity_penalty = complexity_penalties.get(classification.fix_complexity.value, 0)

            return base_score * impact_multiplier + probability_bonus + complexity_penalty

        # Sort by priority score (descending)
        return sorted(classified_issues, key=priority_score, reverse=True)

    def _load_classification_rules(self) -> Dict[str, Dict[str, str]]:
        """Load classification rules for different issue types."""
        return {
            'encoding_error': {
                'category': 'technical',
                'subcategory': 'character_encoding',
                'typical_causes': 'charset_mismatch',
                'common_fixes': 'encoding_conversion'
            },
            'ocr_failure': {
                'category': 'technical',
                'subcategory': 'optical_recognition',
                'typical_causes': 'poor_scan_quality',
                'common_fixes': 'ocr_reprocessing'
            },
            'structure_loss': {
                'category': 'structural',
                'subcategory': 'document_hierarchy',
                'typical_causes': 'format_conversion_loss',
                'common_fixes': 'structure_reconstruction'
            },
            'content_gaps': {
                'category': 'content',
                'subcategory': 'missing_information',
                'typical_causes': 'extraction_incomplete',
                'common_fixes': 'reextraction_with_different_method'
            },
            'table_corruption': {
                'category': 'structural',
                'subcategory': 'tabular_data',
                'typical_causes': 'layout_parsing_failure',
                'common_fixes': 'table_specific_extraction'
            },
            'formatting_loss': {
                'category': 'quality',
                'subcategory': 'visual_formatting',
                'typical_causes': 'format_limitation',
                'common_fixes': 'manual_formatting_recovery'
            },
            'language_inconsistency': {
                'category': 'content',
                'subcategory': 'language_detection',
                'typical_causes': 'mixed_language_content',
                'common_fixes': 'language_specific_processing'
            },
            'incomplete_extraction': {
                'category': 'technical',
                'subcategory': 'extraction_completeness',
                'typical_causes': 'tool_limitation',
                'common_fixes': 'alternative_extraction_tool'
            }
        }

    def _load_impact_weights(self) -> Dict[str, float]:
        """Load weights for impact calculation."""
        return {
            'content_loss': 3.0,
            'structure_loss': 2.5,
            'readability_impact': 2.0,
            'search_impact': 1.5,
            'visual_impact': 1.0,
            'metadata_loss': 0.5
        }

    def _load_fix_strategies(self) -> Dict[str, Dict[str, Any]]:
        """Load fix strategies for different issue types."""
        return {
            'encoding_error': {
                'primary_strategy': 'charset_detection_and_conversion',
                'tools': ['chardet', 'ftfy', 'codecs'],
                'success_rate': 0.85,
                'complexity': 'low'
            },
            'ocr_failure': {
                'primary_strategy': 'alternative_ocr_engine',
                'tools': ['tesseract', 'pytesseract', 'easyocr'],
                'success_rate': 0.70,
                'complexity': 'medium'
            },
            'structure_loss': {
                'primary_strategy': 'manual_structure_annotation',
                'tools': ['custom_parsers', 'rule_based_detection'],
                'success_rate': 0.60,
                'complexity': 'high'
            },
            'content_gaps': {
                'primary_strategy': 'multi_tool_extraction_comparison',
                'tools': ['alternative_extractors', 'manual_review'],
                'success_rate': 0.75,
                'complexity': 'medium'
            },
            'table_corruption': {
                'primary_strategy': 'table_specific_extraction',
                'tools': ['tabula', 'camelot', 'pandas'],
                'success_rate': 0.65,
                'complexity': 'medium'
            }
        }

    def _calculate_impact_score(self, failure_data: Dict[str, Any]) -> float:
        """Calculate impact score for a failure."""
        base_impact = 0.5  # Base impact score

        # Adjust based on failure characteristics
        severity_impact = {
            'critical': 1.0,
            'high': 0.8,
            'medium': 0.6,
            'low': 0.4
        }.get(failure_data.get('severity', 'medium'), 0.5)

        confidence_factor = failure_data.get('confidence', 0.5)

        # Content-specific impacts
        content_length = len(failure_data.get('affected_content', ''))
        content_impact = min(1.0, content_length / 100)  # Normalize to 0-1

        # Position-based impact (earlier in document = higher impact)
        position = failure_data.get('position', 0)
        position_impact = max(0.3, 1.0 - (position / 10000))  # Reduce impact for later positions

        # Combine factors
        impact_score = (severity_impact * 0.4 +
                       confidence_factor * 0.3 +
                       content_impact * 0.2 +
                       position_impact * 0.1)

        return min(1.0, impact_score)

    def _assess_fix_complexity(self, issue_type: str, pattern: str, failure_data: Dict[str, Any]) -> FixComplexity:
        """Assess the complexity of fixing an issue."""
        base_complexities = {
            'encoding_error': FixComplexity.LOW,
            'ocr_failure': FixComplexity.MEDIUM,
            'structure_loss': FixComplexity.HIGH,
            'content_gaps': FixComplexity.MEDIUM,
            'table_corruption': FixComplexity.MEDIUM,
            'formatting_loss': FixComplexity.HIGH,
            'language_inconsistency': FixComplexity.MEDIUM,
            'incomplete_extraction': FixComplexity.HIGH
        }

        base_complexity = base_complexities.get(issue_type, FixComplexity.MEDIUM)

        # Adjust based on specific patterns or context
        if pattern in ['utf8_corruption', 'html_entities']:
            return FixComplexity.LOW
        elif pattern in ['mojibake', 'mixed_scripts']:
            return FixComplexity.HIGH
        elif 'severely_incomplete' in pattern:
            return FixComplexity.EXPERT

        return base_complexity

    def _estimate_fix_probability(self, issue_type: str, pattern: str, confidence: float) -> float:
        """Estimate probability of successful fix."""
        base_probabilities = {
            'encoding_error': 0.85,
            'ocr_failure': 0.65,
            'structure_loss': 0.45,
            'content_gaps': 0.60,
            'table_corruption': 0.55,
            'formatting_loss': 0.30,
            'language_inconsistency': 0.70,
            'incomplete_extraction': 0.40
        }

        base_prob = base_probabilities.get(issue_type, 0.50)

        # Adjust based on detection confidence
        confidence_factor = 0.5 + (confidence * 0.5)  # Scale to 0.5-1.0

        return min(0.95, base_prob * confidence_factor)

    def _identify_dependencies(self, issue_type: str, failure_data: Dict[str, Any]) -> List[str]:
        """Identify dependencies for fixing an issue."""
        dependencies_map = {
            'encoding_error': ['charset_detection_library'],
            'ocr_failure': ['alternative_ocr_engine', 'image_preprocessing'],
            'structure_loss': ['document_analysis_expertise', 'manual_review_capacity'],
            'content_gaps': ['alternative_extraction_tools'],
            'table_corruption': ['table_extraction_library', 'layout_analysis'],
            'incomplete_extraction': ['different_extraction_approach', 'source_document_access']
        }

        return dependencies_map.get(issue_type, [])

    def _identify_required_tools(self, issue_type: str, pattern: str) -> List[str]:
        """Identify tools required to fix an issue."""
        tools_map = {
            'encoding_error': ['chardet', 'ftfy', 'codecs'],
            'ocr_failure': ['tesseract', 'pytesseract', 'pillow'],
            'structure_loss': ['custom_parser', 'regex_tools'],
            'content_gaps': ['alternative_extractor', 'diff_tools'],
            'table_corruption': ['tabula-py', 'camelot-py', 'pandas'],
            'incomplete_extraction': ['multiple_extraction_tools']
        }

        return tools_map.get(issue_type, ['manual_tools'])

    def _get_base_severity(self, issue_type: str) -> str:
        """Get base severity for an issue type."""
        severity_map = {
            'encoding_error': 'high',
            'ocr_failure': 'medium',
            'structure_loss': 'high',
            'content_gaps': 'medium',
            'table_corruption': 'medium',
            'formatting_loss': 'low',
            'language_inconsistency': 'medium',
            'incomplete_extraction': 'critical'
        }

        return severity_map.get(issue_type, 'medium')

    def _calculate_severity_adjustment(self, impact_factors: Dict[str, float]) -> float:
        """Calculate adjustment factor for severity based on impact."""
        weighted_impact = (
            impact_factors.get('affected_content_ratio', 0) * 0.3 +
            impact_factors.get('content_importance', 0.5) * 0.3 +
            impact_factors.get('user_visibility', 0.5) * 0.2 +
            impact_factors.get('fix_urgency', 0.5) * 0.2
        )

        return weighted_impact

    def _apply_severity_adjustment(self, base_severity: str, adjustment: float) -> str:
        """Apply adjustment to base severity."""
        severity_levels = ['low', 'medium', 'high', 'critical']
        current_index = severity_levels.index(base_severity)

        if adjustment > 0.8:
            new_index = min(3, current_index + 1)
        elif adjustment < 0.3:
            new_index = max(0, current_index - 1)
        else:
            new_index = current_index

        return severity_levels[new_index]

    def _get_base_effort(self, complexity: FixComplexity) -> float:
        """Get base effort in hours for complexity level."""
        effort_map = {
            FixComplexity.TRIVIAL: 0.5,
            FixComplexity.LOW: 2.0,
            FixComplexity.MEDIUM: 8.0,
            FixComplexity.HIGH: 24.0,
            FixComplexity.EXPERT: 40.0
        }

        return effort_map.get(complexity, 8.0)

    def _get_severity_multiplier(self, severity: str) -> float:
        """Get effort multiplier based on severity."""
        multipliers = {
            'critical': 1.5,
            'high': 1.2,
            'medium': 1.0,
            'low': 0.8
        }

        return multipliers.get(severity.lower(), 1.0)

    def _get_dependency_multiplier(self, num_dependencies: int) -> float:
        """Get effort multiplier based on number of dependencies."""
        if num_dependencies == 0:
            return 1.0
        elif num_dependencies <= 2:
            return 1.3
        else:
            return 1.6

    def _get_tools_multiplier(self, num_tools: int) -> float:
        """Get effort multiplier based on number of tools required."""
        if num_tools <= 1:
            return 1.0
        elif num_tools <= 3:
            return 1.2
        else:
            return 1.4

    def _get_probability_multiplier(self, fix_probability: float) -> float:
        """Get effort multiplier based on fix probability (lower probability = higher effort)."""
        if fix_probability >= 0.8:
            return 1.0
        elif fix_probability >= 0.6:
            return 1.3
        elif fix_probability >= 0.4:
            return 1.6
        else:
            return 2.0

    def _identify_risk_factors(self, classification: IssueClassification) -> List[str]:
        """Identify risk factors that might complicate the fix."""
        risks = []

        if classification.fix_complexity in [FixComplexity.HIGH, FixComplexity.EXPERT]:
            risks.append("High complexity fix required")

        if classification.fix_probability < 0.5:
            risks.append("Low probability of successful fix")

        if len(classification.dependencies) > 2:
            risks.append("Multiple dependencies required")

        if classification.impact_score > 0.8:
            risks.append("High impact on document quality")

        return risks