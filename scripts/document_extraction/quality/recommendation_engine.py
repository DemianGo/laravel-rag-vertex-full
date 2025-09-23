"""
Recommendation engine for document extraction issue resolution.
"""

from typing import Dict, Any, List, Optional, Tuple
from enum import Enum
from dataclasses import dataclass
import re
from .issue_classifier import IssueClassification, FixComplexity


class ActionType(Enum):
    """Types of recommended actions."""
    REPROCESS = "reprocess"
    TOOL_CHANGE = "tool_change"
    PARAMETER_ADJUST = "parameter_adjust"
    MANUAL_FIX = "manual_fix"
    PREPROCESSING = "preprocessing"
    POSTPROCESSING = "postprocessing"
    VALIDATION = "validation"


class Priority(Enum):
    """Recommendation priorities."""
    URGENT = "urgent"
    HIGH = "high"
    MEDIUM = "medium"
    LOW = "low"
    OPTIONAL = "optional"


@dataclass
class Recommendation:
    """A specific recommendation for fixing an issue."""
    recommendation_id: str
    for_issue: str
    action_type: ActionType
    action: str
    priority: Priority
    success_probability: float
    estimated_effort: str
    prerequisites: List[str]
    tools_required: List[str]
    step_by_step: List[str]
    validation_criteria: List[str]
    alternatives: List[str]
    risk_factors: List[str]


class RecommendationEngine:
    """Generate actionable recommendations for fixing document extraction issues."""

    def __init__(self):
        self.fix_templates = self._load_fix_templates()
        self.tool_recommendations = self._load_tool_recommendations()
        self.priority_matrix = self._load_priority_matrix()

    def generate_recommendations(self, classified_issues: List[Dict[str, Any]]) -> List[Recommendation]:
        """
        Generate specific recommendations for classified issues.

        Args:
            classified_issues: List of issues with classifications

        Returns:
            List of actionable recommendations
        """
        recommendations = []

        for i, issue in enumerate(classified_issues):
            issue_recs = self._generate_issue_recommendations(issue, i + 1)
            recommendations.extend(issue_recs)

        # Remove duplicates and optimize recommendations
        recommendations = self._deduplicate_recommendations(recommendations)
        recommendations = self._optimize_recommendation_order(recommendations)

        return recommendations

    def prioritize_fixes(self, recommendations: List[Recommendation]) -> List[Recommendation]:
        """
        Prioritize recommendations based on impact, effort, and success probability.

        Args:
            recommendations: List of recommendations

        Returns:
            Prioritized list of recommendations
        """
        def priority_score(rec: Recommendation) -> float:
            priority_weights = {
                Priority.URGENT: 100,
                Priority.HIGH: 75,
                Priority.MEDIUM: 50,
                Priority.LOW: 25,
                Priority.OPTIONAL: 10
            }

            base_score = priority_weights.get(rec.priority, 50)
            probability_bonus = rec.success_probability * 30
            effort_penalty = self._get_effort_penalty(rec.estimated_effort)

            return base_score + probability_bonus - effort_penalty

        return sorted(recommendations, key=priority_score, reverse=True)

    def estimate_success_probability(self, recommendation: Recommendation,
                                   context: Dict[str, Any]) -> float:
        """
        Estimate probability of success for a recommendation.

        Args:
            recommendation: The recommendation to evaluate
            context: Additional context about the document/environment

        Returns:
            Success probability (0.0 to 1.0)
        """
        base_probability = recommendation.success_probability

        # Adjust based on context factors
        adjustments = []

        # Tool availability adjustment
        if self._check_tool_availability(recommendation.tools_required, context):
            adjustments.append(0.1)
        else:
            adjustments.append(-0.2)

        # Prerequisites met adjustment
        if self._check_prerequisites_met(recommendation.prerequisites, context):
            adjustments.append(0.1)
        else:
            adjustments.append(-0.15)

        # Document type compatibility
        doc_type = context.get('file_type', 'unknown')
        if self._is_action_compatible(recommendation.action_type, doc_type):
            adjustments.append(0.05)
        else:
            adjustments.append(-0.1)

        # Apply adjustments
        final_probability = base_probability + sum(adjustments)
        return max(0.1, min(0.95, final_probability))

    def generate_action_plan(self, prioritized_recommendations: List[Recommendation]) -> Dict[str, Any]:
        """
        Generate a comprehensive action plan from prioritized recommendations.

        Args:
            prioritized_recommendations: Prioritized list of recommendations

        Returns:
            Structured action plan
        """
        plan = {
            "plan_id": f"extraction_fix_plan_{len(prioritized_recommendations)}",
            "total_recommendations": len(prioritized_recommendations),
            "phases": self._group_recommendations_into_phases(prioritized_recommendations),
            "estimated_timeline": self._estimate_total_timeline(prioritized_recommendations),
            "resource_requirements": self._calculate_resource_requirements(prioritized_recommendations),
            "success_indicators": self._define_success_indicators(prioritized_recommendations),
            "rollback_plan": self._create_rollback_plan(prioritized_recommendations)
        }

        return plan

    def _generate_issue_recommendations(self, issue: Dict[str, Any], issue_number: int) -> List[Recommendation]:
        """Generate recommendations for a specific issue."""
        recommendations = []

        issue_type = issue.get('type', 'unknown')
        pattern = issue.get('pattern', '')
        classification = issue.get('classification')

        # Get primary recommendation template
        primary_template = self.fix_templates.get(issue_type, {})
        if primary_template:
            primary_rec = self._create_recommendation_from_template(
                issue, primary_template, issue_number, 'primary'
            )
            recommendations.append(primary_rec)

        # Generate alternative recommendations
        alternatives = self._get_alternative_approaches(issue_type, pattern)
        for i, alt_template in enumerate(alternatives):
            alt_rec = self._create_recommendation_from_template(
                issue, alt_template, issue_number, f'alt_{i+1}'
            )
            recommendations.append(alt_rec)

        # Add validation recommendations
        validation_rec = self._create_validation_recommendation(issue, issue_number)
        if validation_rec:
            recommendations.append(validation_rec)

        return recommendations

    def _create_recommendation_from_template(self, issue: Dict[str, Any],
                                           template: Dict[str, Any],
                                           issue_number: int, variant: str) -> Recommendation:
        """Create a recommendation from a template."""
        issue_id = issue.get('failure_id', f'ISSUE_{issue_number:03d}')
        rec_id = f"REC_{issue_number:03d}_{variant.upper()}"

        # Customize template based on specific issue characteristics
        customized_template = self._customize_template(template, issue)

        return Recommendation(
            recommendation_id=rec_id,
            for_issue=issue_id,
            action_type=ActionType(customized_template.get('action_type', 'manual_fix')),
            action=customized_template.get('action', 'Manual review required'),
            priority=Priority(customized_template.get('priority', 'medium')),
            success_probability=customized_template.get('success_probability', 0.5),
            estimated_effort=customized_template.get('estimated_effort', 'medium'),
            prerequisites=customized_template.get('prerequisites', []),
            tools_required=customized_template.get('tools_required', []),
            step_by_step=customized_template.get('steps', []),
            validation_criteria=customized_template.get('validation', []),
            alternatives=customized_template.get('alternatives', []),
            risk_factors=customized_template.get('risks', [])
        )

    def _load_fix_templates(self) -> Dict[str, Dict[str, Any]]:
        """Load fix templates for different issue types."""
        return {
            'encoding_error': {
                'action_type': 'reprocess',
                'action': 'Detect correct encoding and reprocess document',
                'priority': 'high',
                'success_probability': 0.85,
                'estimated_effort': 'low',
                'prerequisites': ['chardet library available'],
                'tools_required': ['chardet', 'ftfy'],
                'steps': [
                    'Run charset detection on original file',
                    'Identify most likely encoding',
                    'Reprocess document with detected encoding',
                    'Compare output quality with original extraction',
                    'Apply text correction if needed'
                ],
                'validation': [
                    'No more replacement characters (�) in output',
                    'Text appears readable in expected language',
                    'Special characters display correctly'
                ],
                'alternatives': [
                    'Try multiple encoding candidates',
                    'Use ftfy for automatic fixing',
                    'Manual encoding specification'
                ],
                'risks': ['May not work for mixed encodings', 'Original file access required']
            },
            'ocr_failure': {
                'action_type': 'tool_change',
                'action': 'Use alternative OCR engine with preprocessing',
                'priority': 'medium',
                'success_probability': 0.70,
                'estimated_effort': 'medium',
                'prerequisites': ['Alternative OCR tool available', 'Original document images'],
                'tools_required': ['tesseract', 'easyocr', 'pillow'],
                'steps': [
                    'Preprocess images (contrast, resolution adjustment)',
                    'Try alternative OCR engines (Tesseract, EasyOCR)',
                    'Compare results from different engines',
                    'Use ensemble approach for best results',
                    'Post-process to fix common OCR errors'
                ],
                'validation': [
                    'Text readability improved',
                    'Fewer obvious OCR errors',
                    'Better character recognition accuracy'
                ],
                'alternatives': [
                    'Manual transcription of key sections',
                    'Hybrid OCR with manual correction',
                    'Commercial OCR services'
                ],
                'risks': ['Time intensive', 'May still have recognition errors']
            },
            'structure_loss': {
                'action_type': 'manual_fix',
                'action': 'Manually reconstruct document structure',
                'priority': 'high',
                'success_probability': 0.60,
                'estimated_effort': 'high',
                'prerequisites': ['Domain expertise', 'Original document access'],
                'tools_required': ['text_editor', 'regex_tools', 'structure_templates'],
                'steps': [
                    'Analyze original document structure',
                    'Identify missed headers and sections',
                    'Create rules for structure detection',
                    'Apply rules to extract structure',
                    'Validate against original document'
                ],
                'validation': [
                    'All major sections identified',
                    'Hierarchical structure preserved',
                    'Section boundaries accurate'
                ],
                'alternatives': [
                    'Use different extraction tool',
                    'Combine multiple extraction approaches',
                    'Accept structure loss and focus on content'
                ],
                'risks': ['Very time consuming', 'Requires manual expertise']
            },
            'content_gaps': {
                'action_type': 'reprocess',
                'action': 'Compare multiple extraction methods',
                'priority': 'medium',
                'success_probability': 0.75,
                'estimated_effort': 'medium',
                'prerequisites': ['Alternative extraction tools'],
                'tools_required': ['multiple_extractors', 'diff_tools'],
                'steps': [
                    'Extract content using different tools',
                    'Compare extracted content sections',
                    'Identify sections with gaps',
                    'Merge best parts from each extraction',
                    'Validate completeness'
                ],
                'validation': [
                    'No major content sections missing',
                    'Text flows logically',
                    'Key information preserved'
                ],
                'alternatives': [
                    'Focus on critical sections only',
                    'Manual gap filling',
                    'Accept incomplete extraction'
                ],
                'risks': ['May not recover all missing content']
            },
            'table_corruption': {
                'action_type': 'tool_change',
                'action': 'Use specialized table extraction tools',
                'priority': 'medium',
                'success_probability': 0.65,
                'estimated_effort': 'medium',
                'prerequisites': ['Table extraction libraries'],
                'tools_required': ['tabula', 'camelot', 'pandas'],
                'steps': [
                    'Identify table regions in original document',
                    'Extract tables using specialized tools',
                    'Convert to structured format (CSV, JSON)',
                    'Validate table structure and content',
                    'Integrate with main text extraction'
                ],
                'validation': [
                    'Tables maintain row/column structure',
                    'Data values are accurate',
                    'Headers properly identified'
                ],
                'alternatives': [
                    'Manual table reconstruction',
                    'Screenshot and manual data entry',
                    'Accept table as unstructured text'
                ],
                'risks': ['Complex tables may still fail', 'Tool-specific limitations']
            },
            'incomplete_extraction': {
                'action_type': 'tool_change',
                'action': 'Try alternative extraction approach',
                'priority': 'high',
                'success_probability': 0.40,
                'estimated_effort': 'high',
                'prerequisites': ['Alternative tools available', 'Original file access'],
                'tools_required': ['alternative_extractors', 'file_converters'],
                'steps': [
                    'Identify why extraction is incomplete',
                    'Try different extraction libraries',
                    'Convert file to different format first',
                    'Use format-specific extraction methods',
                    'Compare completeness metrics'
                ],
                'validation': [
                    'Significant increase in extracted content',
                    'Key document sections present',
                    'Content quality maintained'
                ],
                'alternatives': [
                    'Manual extraction of key sections',
                    'Partial extraction with clear limitations',
                    'Request different file format'
                ],
                'risks': ['May still not achieve complete extraction', 'High effort investment']
            }
        }

    def _load_tool_recommendations(self) -> Dict[str, List[str]]:
        """Load tool recommendations by issue type."""
        return {
            'encoding_error': ['chardet', 'ftfy', 'codecs', 'iconv'],
            'ocr_failure': ['tesseract-ocr', 'easyocr', 'paddleocr', 'aws-textract'],
            'structure_loss': ['beautifulsoup4', 'lxml', 'pypandoc', 'custom-parsers'],
            'content_gaps': ['pdfplumber', 'pymupdf', 'textract', 'apache-tika'],
            'table_corruption': ['tabula-py', 'camelot-py', 'pdfplumber', 'pymupdf'],
            'incomplete_extraction': ['pypandoc', 'mammoth', 'python-docx', 'openpyxl']
        }

    def _load_priority_matrix(self) -> Dict[str, Dict[str, str]]:
        """Load priority matrix for different combinations."""
        return {
            'critical_high_impact': 'urgent',
            'critical_medium_impact': 'high',
            'high_high_impact': 'high',
            'high_medium_impact': 'medium',
            'medium_any_impact': 'medium',
            'low_any_impact': 'low'
        }

    def _customize_template(self, template: Dict[str, Any], issue: Dict[str, Any]) -> Dict[str, Any]:
        """Customize template based on specific issue characteristics."""
        customized = template.copy()

        # Adjust priority based on issue severity and position
        severity = issue.get('severity', 'medium')
        position = issue.get('position', 0)

        if severity == 'critical':
            customized['priority'] = 'urgent'
        elif severity == 'high' and position < 1000:  # Early in document
            customized['priority'] = 'high'

        # Adjust success probability based on confidence
        confidence = issue.get('confidence', 0.5)
        base_prob = customized.get('success_probability', 0.5)
        customized['success_probability'] = min(0.95, base_prob * (0.5 + confidence * 0.5))

        # Add issue-specific context to steps
        affected_content = issue.get('affected_content', '')
        if affected_content:
            step_context = f"Focus on content: '{affected_content[:50]}...'"
            customized['steps'] = [step_context] + customized.get('steps', [])

        return customized

    def _get_alternative_approaches(self, issue_type: str, pattern: str) -> List[Dict[str, Any]]:
        """Get alternative approaches for an issue type."""
        alternatives_map = {
            'encoding_error': [
                {
                    'action_type': 'postprocessing',
                    'action': 'Apply text correction algorithms',
                    'priority': 'medium',
                    'success_probability': 0.60,
                    'estimated_effort': 'low',
                    'tools_required': ['ftfy', 'unidecode'],
                    'steps': ['Apply ftfy text correction', 'Use unidecode for fallback'],
                    'validation': ['Text readability improved']
                }
            ],
            'ocr_failure': [
                {
                    'action_type': 'preprocessing',
                    'action': 'Enhance image quality before OCR',
                    'priority': 'medium',
                    'success_probability': 0.50,
                    'estimated_effort': 'low',
                    'tools_required': ['pillow', 'opencv'],
                    'steps': ['Enhance contrast', 'Adjust resolution', 'Reduce noise'],
                    'validation': ['Clearer text in processed image']
                }
            ]
        }

        return alternatives_map.get(issue_type, [])

    def _create_validation_recommendation(self, issue: Dict[str, Any], issue_number: int) -> Optional[Recommendation]:
        """Create a validation recommendation for an issue."""
        issue_id = issue.get('failure_id', f'ISSUE_{issue_number:03d}')
        rec_id = f"REC_{issue_number:03d}_VALIDATION"

        return Recommendation(
            recommendation_id=rec_id,
            for_issue=issue_id,
            action_type=ActionType.VALIDATION,
            action=f"Validate fix for {issue.get('type', 'issue')}",
            priority=Priority.LOW,
            success_probability=0.90,
            estimated_effort='low',
            prerequisites=['Fix implementation completed'],
            tools_required=['validation_scripts'],
            step_by_step=[
                'Compare before/after extraction quality',
                'Check specific issue pattern is resolved',
                'Verify no new issues introduced',
                'Document improvement metrics'
            ],
            validation_criteria=[
                'Original issue no longer present',
                'Overall quality maintained or improved',
                'No regression in other areas'
            ],
            alternatives=['Manual quality review'],
            risk_factors=['May not catch subtle quality degradation']
        )

    def _deduplicate_recommendations(self, recommendations: List[Recommendation]) -> List[Recommendation]:
        """Remove duplicate or very similar recommendations."""
        seen_actions = set()
        unique_recommendations = []

        for rec in recommendations:
            action_key = (rec.action_type.value, rec.action.lower())
            if action_key not in seen_actions:
                seen_actions.add(action_key)
                unique_recommendations.append(rec)

        return unique_recommendations

    def _optimize_recommendation_order(self, recommendations: List[Recommendation]) -> List[Recommendation]:
        """Optimize the order of recommendations for efficiency."""
        # Group by action type to batch similar actions
        grouped = {}
        for rec in recommendations:
            action_type = rec.action_type.value
            if action_type not in grouped:
                grouped[action_type] = []
            grouped[action_type].append(rec)

        # Preferred order of action types
        preferred_order = [
            'preprocessing',
            'reprocess',
            'tool_change',
            'parameter_adjust',
            'postprocessing',
            'manual_fix',
            'validation'
        ]

        optimized = []
        for action_type in preferred_order:
            if action_type in grouped:
                # Sort within group by priority
                group_sorted = sorted(grouped[action_type],
                                    key=lambda x: list(Priority).index(x.priority))
                optimized.extend(group_sorted)

        # Add any remaining action types not in preferred order
        for action_type, recs in grouped.items():
            if action_type not in preferred_order:
                optimized.extend(recs)

        return optimized

    def _check_tool_availability(self, tools: List[str], context: Dict[str, Any]) -> bool:
        """Check if required tools are available."""
        available_tools = context.get('available_tools', [])
        return all(tool in available_tools for tool in tools)

    def _check_prerequisites_met(self, prerequisites: List[str], context: Dict[str, Any]) -> bool:
        """Check if prerequisites are met."""
        # Simplified check - in real implementation, would check specific conditions
        return len(prerequisites) <= 2  # Assume simpler prerequisites are more likely to be met

    def _is_action_compatible(self, action_type: ActionType, doc_type: str) -> bool:
        """Check if action type is compatible with document type."""
        compatibility_map = {
            'pdf': [ActionType.REPROCESS, ActionType.TOOL_CHANGE, ActionType.PREPROCESSING],
            'docx': [ActionType.REPROCESS, ActionType.TOOL_CHANGE, ActionType.PARAMETER_ADJUST],
            'html': [ActionType.REPROCESS, ActionType.POSTPROCESSING, ActionType.PARAMETER_ADJUST],
            'txt': [ActionType.POSTPROCESSING, ActionType.PARAMETER_ADJUST, ActionType.VALIDATION]
        }

        compatible_actions = compatibility_map.get(doc_type.lower(), list(ActionType))
        return action_type in compatible_actions

    def _group_recommendations_into_phases(self, recommendations: List[Recommendation]) -> List[Dict[str, Any]]:
        """Group recommendations into logical phases."""
        phases = [
            {"name": "Quick Fixes", "recommendations": []},
            {"name": "Tool Changes", "recommendations": []},
            {"name": "Manual Interventions", "recommendations": []},
            {"name": "Validation", "recommendations": []}
        ]

        for rec in recommendations:
            if rec.action_type in [ActionType.POSTPROCESSING, ActionType.PARAMETER_ADJUST]:
                phases[0]["recommendations"].append(rec.recommendation_id)
            elif rec.action_type in [ActionType.REPROCESS, ActionType.TOOL_CHANGE, ActionType.PREPROCESSING]:
                phases[1]["recommendations"].append(rec.recommendation_id)
            elif rec.action_type == ActionType.MANUAL_FIX:
                phases[2]["recommendations"].append(rec.recommendation_id)
            else:
                phases[3]["recommendations"].append(rec.recommendation_id)

        return [phase for phase in phases if phase["recommendations"]]

    def _estimate_total_timeline(self, recommendations: List[Recommendation]) -> Dict[str, Any]:
        """Estimate total timeline for all recommendations."""
        effort_hours = {
            'low': 2,
            'medium': 8,
            'high': 24,
            'expert': 40
        }

        total_hours = sum(effort_hours.get(rec.estimated_effort, 8) for rec in recommendations)

        return {
            'total_hours': total_hours,
            'estimated_days': max(1, total_hours // 8),
            'complexity': 'high' if total_hours > 40 else 'medium' if total_hours > 16 else 'low'
        }

    def _calculate_resource_requirements(self, recommendations: List[Recommendation]) -> Dict[str, Any]:
        """Calculate resource requirements for recommendations."""
        all_tools = set()
        all_prereqs = set()

        for rec in recommendations:
            all_tools.update(rec.tools_required)
            all_prereqs.update(rec.prerequisites)

        return {
            'tools_needed': list(all_tools),
            'prerequisites': list(all_prereqs),
            'estimated_cost': 'low',  # Simplified
            'expertise_required': self._assess_expertise_level(recommendations)
        }

    def _define_success_indicators(self, recommendations: List[Recommendation]) -> List[str]:
        """Define success indicators for the action plan."""
        return [
            "All critical issues resolved",
            "Document extraction quality improved",
            "No new issues introduced",
            "Validation criteria met for all fixes"
        ]

    def _create_rollback_plan(self, recommendations: List[Recommendation]) -> Dict[str, Any]:
        """Create rollback plan in case of issues."""
        return {
            'backup_strategy': 'Keep original extraction results',
            'rollback_triggers': ['Quality degradation', 'New critical issues', 'Process failure'],
            'rollback_steps': [
                'Stop current fixes',
                'Restore original extraction',
                'Document what went wrong',
                'Revise approach'
            ]
        }

    def _get_effort_penalty(self, effort: str) -> float:
        """Get penalty score for effort level."""
        penalties = {
            'low': 0,
            'medium': 10,
            'high': 25,
            'expert': 40
        }
        return penalties.get(effort.lower(), 15)

    def _assess_expertise_level(self, recommendations: List[Recommendation]) -> str:
        """Assess required expertise level for recommendations."""
        complexity_levels = [rec.estimated_effort for rec in recommendations]

        if 'expert' in complexity_levels:
            return 'expert'
        elif 'high' in complexity_levels:
            return 'advanced'
        elif 'medium' in complexity_levels:
            return 'intermediate'
        else:
            return 'beginner'