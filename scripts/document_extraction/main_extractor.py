#!/usr/bin/env python3
"""
Main document extractor CLI orchestrator.
Automatically detects file type and routes to appropriate extractor.
"""

import argparse
import json
import sys
from pathlib import Path
from typing import Dict, Any, List, Optional

# Import extractors
from extract import extract_pdf_text
from office_extractor import extract_office_document
from text_extractor import extract_text_document
from web_extractor import extract_web_document

# Import Phase 3A modules
from utils.detector import detect_document_type
from utils.language_detector import detect_language
from quality.analyzer import analyze_quality, QualityAnalyzer
from quality.metadata_extractor import MetadataExtractor
from quality.reporter import QualityReporter

# Import Phase 3B modules
from quality.structure_analyzer import StructureAnalyzer
from quality.content_parser import ContentParser
from quality.hierarchy_mapper import HierarchyMapper
from utils.text_patterns import TextPatternRecognizer, detect_titles, detect_lists
from utils.section_detector import SectionDetector, detect_sections


def detect_file_type(file_path: str) -> str:
    """Detect file type based on extension (legacy compatibility)."""
    path = Path(file_path)
    extension = path.suffix.lower()

    file_type_map = {
        '.pdf': 'pdf',
        '.docx': 'docx',
        '.doc': 'docx',
        '.xlsx': 'xlsx',
        '.xls': 'xlsx',
        '.pptx': 'pptx',
        '.ppt': 'pptx',
        '.txt': 'txt',
        '.csv': 'csv',
        '.rtf': 'rtf',
        '.html': 'html',
        '.htm': 'html',
        '.xml': 'xml'
    }

    return file_type_map.get(extension, 'unknown')


def extract_document(file_path: str, detailed_analysis: bool = False, structure_analysis: bool = False, failure_analysis: bool = False) -> Dict[str, Any]:
    """
    Main extraction function that routes to appropriate extractor.

    Args:
        file_path: Path to the document to extract

    Returns:
        Standardized extraction result with quality metrics
    """
    if not Path(file_path).exists():
        return {
            "success": False,
            "file_type": "unknown",
            "extracted_text": "",
            "quality_metrics": {
                "extraction_success_rate": 0,
                "total_pages": 0,
                "pages_processed": 0,
                "quality_rating": "poor"
            },
            "error": f"File not found: {file_path}"
        }

    # Use advanced detection if detailed analysis requested
    if detailed_analysis:
        doc_type_info = detect_document_type(file_path)
        file_type = doc_type_info.get('primary_type', 'unknown')
    else:
        file_type = detect_file_type(file_path)
        doc_type_info = None

    if file_type == 'unknown':
        return {
            "success": False,
            "file_type": file_type,
            "extracted_text": "",
            "quality_metrics": {
                "extraction_success_rate": 0,
                "total_pages": 0,
                "pages_processed": 0,
                "quality_rating": "poor"
            },
            "error": f"Unsupported file type: {Path(file_path).suffix}"
        }

    # Route to appropriate extractor
    try:
        if file_type == 'pdf':
            extracted_text = extract_pdf_text(file_path)
            result = {
                "success": True,
                "extracted_text": extracted_text,
                "error": None
            }
        elif file_type in ['docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt']:
            result = extract_office_document(file_path)
        elif file_type in ['txt', 'csv', 'rtf']:
            result = extract_text_document(file_path)
        elif file_type in ['html', 'xml']:
            result = extract_web_document(file_path)
        else:
            return {
                "success": False,
                "file_type": file_type,
                "extracted_text": "",
                "quality_metrics": {
                    "extraction_success_rate": 0,
                    "total_pages": 0,
                    "pages_processed": 0,
                    "quality_rating": "poor"
                },
                "error": f"No extractor available for file type: {file_type}"
            }

        if not result['success']:
            return {
                "success": False,
                "file_type": file_type,
                "extracted_text": "",
                "quality_metrics": {
                    "extraction_success_rate": 0,
                    "total_pages": 0,
                    "pages_processed": 0,
                    "quality_rating": "poor"
                },
                "error": result.get('error', 'Unknown extraction error')
            }

        # Analyze quality of extracted text
        extracted_text = result.get('extracted_text', '')
        total_pages = result.get('total_pages', None)  # PDF extractor might provide this

        if detailed_analysis or structure_analysis or failure_analysis:
            # Use enhanced analysis
            analyzer = QualityAnalyzer()
            quality_metrics = analyzer.analyze_detailed(extracted_text, file_type, total_pages)

            # Extract metadata
            metadata_extractor = MetadataExtractor()
            metadata = metadata_extractor.extract_metadata(file_path, file_type)

            # Detect language
            language_info = detect_language(extracted_text[:1000])  # Sample first 1000 chars

            result_data = {
                "success": True,
                "file_type": file_type,
                "extracted_text": extracted_text,
                "quality_metrics": quality_metrics,
                "metadata": metadata,
                "language_info": language_info,
                "document_type_info": doc_type_info,
                "error": None
            }

            # Add structure analysis if requested
            if structure_analysis:
                try:
                    # Perform comprehensive structure analysis
                    structure_analyzer = StructureAnalyzer()
                    structure_analysis_result = structure_analyzer.analyze_document_structure(extracted_text, file_type)

                    # Parse content semantically
                    content_parser = ContentParser()
                    parsed_content = content_parser.parse_content(extracted_text, file_type)

                    # Map hierarchy
                    hierarchy_mapper = HierarchyMapper()
                    sections = structure_analysis_result.get('sections_detected', [])
                    headers = structure_analysis_result.get('headers_detected', [])

                    # Ensure sections is a list, not an integer
                    if not isinstance(sections, list):
                        sections = []
                    if not isinstance(headers, list):
                        headers = []

                    if sections:
                        document_tree = hierarchy_mapper.build_document_tree(sections, headers)
                        toc = hierarchy_mapper.generate_toc(document_tree)
                        structure_validation = hierarchy_mapper.validate_structure(document_tree)
                    else:
                        document_tree = {"sections": [], "table_of_contents": [], "navigation_tree": {}}
                        toc = {"entries": [], "statistics": {}, "total_entries": 0}
                        structure_validation = {"is_valid": False, "issues": ["No sections detected"]}

                    # Detect text patterns
                    try:
                        pattern_recognizer = TextPatternRecognizer()
                        text_patterns = pattern_recognizer.recognize_patterns(extracted_text)
                    except Exception:
                        text_patterns = []

                    # Detect sections with advanced algorithm
                    try:
                        section_detector = SectionDetector()
                        advanced_sections = section_detector.detect_sections(extracted_text, file_type)
                    except Exception:
                        advanced_sections = {}

                    # Compile structure analysis
                    result_data["structure_analysis"] = {
                        "document_hierarchy": {
                            "sections": document_tree.get("root", []),
                            "table_of_contents": toc.get("entries", []),
                            "navigation_tree": document_tree.get("navigation", {}),
                            "max_depth": document_tree.get("metadata", {}).get("max_depth", 0),
                            "total_sections": len(document_tree.get("root", []))
                        },
                        "content_elements": {
                            "paragraphs": structure_analysis_result.get("content_elements", {}).get("paragraphs", {}),
                            "lists": structure_analysis_result.get("content_elements", {}).get("lists", {}),
                            "tables": structure_analysis_result.get("content_elements", {}).get("tables", {}),
                            "citations": structure_analysis_result.get("content_elements", {}).get("citations", {}),
                            "footnotes": structure_analysis_result.get("content_elements", {}).get("footnotes", {}),
                            "quotes": structure_analysis_result.get("content_elements", {}).get("quotes", {}),
                            "code_blocks": structure_analysis_result.get("content_elements", {}).get("code_blocks", {})
                        },
                        "structural_quality": {
                            "hierarchy_consistency": structure_analysis_result.get("structural_quality", {}).get("hierarchy_consistency", 0),
                            "section_balance": structure_analysis_result.get("structural_quality", {}).get("section_balance", 0),
                            "logical_flow_score": structure_analysis_result.get("structural_quality", {}).get("logical_flow_score", 0),
                            "completeness_score": structure_analysis_result.get("structural_quality", {}).get("completeness_score", 0),
                            "overall_score": structure_analysis_result.get("structural_quality", {}).get("overall_score", 0),
                            "completeness_indicators": structure_analysis_result.get("structural_quality", {}).get("completeness_indicators", [])
                        },
                        "text_patterns": {
                            "total_patterns": len(text_patterns) if text_patterns and isinstance(text_patterns, list) else 0,
                            "pattern_types": list(set(p.get("type", "unknown") for p in text_patterns)) if text_patterns and isinstance(text_patterns, list) else [],
                            "titles_detected": len([p for p in text_patterns if p.get("type") == "title"]) if text_patterns and isinstance(text_patterns, list) else 0,
                            "lists_detected": len([p for p in text_patterns if p.get("type") == "list_item"]) if text_patterns and isinstance(text_patterns, list) else 0,
                            "citations_detected": len([p for p in text_patterns if p.get("type") == "citation"]) if text_patterns and isinstance(text_patterns, list) else 0
                        },
                        "semantic_content": {
                            "document_type": parsed_content.get("document_type", "unknown") if isinstance(parsed_content, dict) else "unknown",
                            "elements_detected": len(parsed_content.get("elements", [])) if isinstance(parsed_content, dict) and parsed_content.get("elements") else 0,
                            "structure_indicators": parsed_content.get("structure_indicators", {}) if isinstance(parsed_content, dict) else {}
                        },
                        "advanced_sections": {
                            "total_sections": advanced_sections.get("total_sections", 0) if isinstance(advanced_sections, dict) else 0,
                            "detection_confidence": advanced_sections.get("detection_confidence", 0) if isinstance(advanced_sections, dict) else 0,
                            "section_types": advanced_sections.get("section_types", {}) if isinstance(advanced_sections, dict) else {},
                            "document_structure_score": advanced_sections.get("metadata", {}).get("document_structure_score", 0) if isinstance(advanced_sections, dict) and isinstance(advanced_sections.get("metadata", {}), dict) else 0
                        },
                        "validation": structure_validation if structure_validation else {"is_valid": False, "issues": ["Structure validation failed"]}
                    }

                except Exception as e:
                    # Structure analysis failed, add error info but continue
                    result_data["structure_analysis_error"] = f"Structure analysis failed: {str(e)}"

            # Generate comprehensive report
            if detailed_analysis:
                reporter = QualityReporter()
                extraction_result = {
                    "success": True,
                    "file_type": file_type,
                    "extracted_text": extracted_text
                }

                quality_report = reporter.generate_report(
                    extraction_result, metadata, quality_metrics, language_info, doc_type_info
                )
                result_data["quality_report"] = quality_report

            # Add failure analysis if requested
            if failure_analysis:
                try:
                    from quality.failure_detector import FailureDetector
                    from quality.issue_classifier import IssueClassifier
                    from quality.recommendation_engine import RecommendationEngine
                    from utils.error_localizer import create_document_mapping
                    from utils.quality_validators import validate_section_quality

                    # Detect failures
                    failure_detector = FailureDetector()
                    detected_failures = failure_detector.detect_extraction_failures(
                        extracted_text,
                        {"file_type": file_type, "file_size": metadata.get("file_size", 0)},
                        structure_analysis_result if structure_analysis else None
                    )

                    # Classify issues
                    classifier = IssueClassifier()
                    classified_issues = []

                    for failure in detected_failures:
                        classification = classifier.classify_issue_type(failure)
                        classified_issues.append({
                            **failure,
                            "classification": classification
                        })

                    # Generate recommendations
                    recommendation_engine = RecommendationEngine()
                    recommendations = recommendation_engine.generate_recommendations(classified_issues)
                    prioritized_recommendations = recommendation_engine.prioritize_fixes(recommendations)

                    # Create document mapping
                    doc_mapping = create_document_mapping(
                        extracted_text,
                        {"file_size": metadata.get("file_size", 0), "file_type": file_type},
                        structure_analysis_result if structure_analysis else None
                    )

                    # Calculate overall quality score
                    overall_quality_score = _calculate_overall_failure_quality_score(
                        classified_issues, quality_metrics
                    )

                    # Analyze quality breakdown by sections
                    sections_quality_analysis = _analyze_sections_quality(
                        structure_analysis_result.get('sections_detected', []) if structure_analysis else [],
                        extracted_text
                    )

                    # Generate actionable insights
                    actionable_insights = _generate_actionable_insights(
                        classified_issues, prioritized_recommendations, sections_quality_analysis
                    )

                    # Compile failure analysis
                    result_data["failure_analysis"] = {
                        "overall_quality_score": overall_quality_score,
                        "detected_issues": [
                            {
                                "issue_id": issue.get("failure_id", "UNKNOWN"),
                                "type": issue.get("type", "unknown").upper(),
                                "severity": issue.get("severity", "medium").upper(),
                                "location": {
                                    "section": _map_position_to_section(
                                        issue.get("position", 0),
                                        structure_analysis_result.get('sections_detected', []) if structure_analysis else []
                                    ),
                                    "position": issue.get("position", 0),
                                    "page_estimate": max(1, (issue.get("position", 0) // 2000) + 1),
                                    "line_estimate": issue.get("position", 0) // 80 + 1
                                },
                                "description": issue.get("description", "Issue detected"),
                                "affected_content": issue.get("affected_content", "")[:100],
                                "confidence": issue.get("confidence", 0.5)
                            }
                            for issue in classified_issues
                        ],
                        "recommendations": [
                            {
                                "recommendation_id": rec.recommendation_id,
                                "for_issue": rec.for_issue,
                                "action": rec.action,
                                "priority": rec.priority.value,
                                "success_probability": rec.success_probability,
                                "estimated_effort": rec.estimated_effort
                            }
                            for rec in prioritized_recommendations[:10]  # Top 10 recommendations
                        ],
                        "quality_breakdown": {
                            "sections_analysis": sections_quality_analysis,
                            "content_integrity": {
                                "text_completeness": _estimate_text_completeness(extracted_text, metadata),
                                "structure_preservation": _estimate_structure_preservation(structure_analysis_result if structure_analysis else {}),
                                "formatting_retention": _estimate_formatting_retention(file_type, extracted_text)
                            }
                        },
                        "actionable_insights": actionable_insights
                    }

                except Exception as e:
                    result_data["failure_analysis_error"] = f"Failure analysis failed: {str(e)}"

            return result_data
        else:
            # Use basic analysis for backward compatibility
            quality_metrics = analyze_quality(extracted_text, file_type, total_pages)

            return {
                "success": True,
                "file_type": file_type,
                "extracted_text": extracted_text,
                "quality_metrics": quality_metrics,
                "error": None
            }

    except Exception as e:
        return {
            "success": False,
            "file_type": file_type,
            "extracted_text": "",
            "quality_metrics": {
                "extraction_success_rate": 0,
                "total_pages": 0,
                "pages_processed": 0,
                "quality_rating": "poor"
            },
            "error": f"Extraction failed: {str(e)}"
        }


def _calculate_overall_failure_quality_score(classified_issues: List[Dict[str, Any]],
                                            quality_metrics: Dict[str, Any]) -> float:
    """Calculate overall quality score considering failures."""
    base_score = quality_metrics.get('extraction_success_rate', 50) / 100.0

    if not classified_issues:
        return base_score

    # Penalty based on issue severity
    severity_penalties = {'critical': 0.3, 'high': 0.2, 'medium': 0.1, 'low': 0.05}
    total_penalty = sum(severity_penalties.get(issue.get('severity', 'medium').lower(), 0.1)
                       for issue in classified_issues)

    # Cap penalty at 0.8 (keep at least 20% score)
    total_penalty = min(0.8, total_penalty)

    return max(0.0, base_score - total_penalty)


def _analyze_sections_quality(sections: List[Dict[str, Any]], text: str) -> Dict[str, Any]:
    """Analyze quality of individual sections."""
    if not isinstance(sections, list) or not sections:
        return {
            "total_sections": 0,
            "high_quality": 0,
            "medium_quality": 0,
            "low_quality": 0
        }

    from utils.quality_validators import validate_section_quality

    quality_counts = {"high": 0, "medium": 0, "low": 0}

    for section in sections:
        start_pos = section.get('start_position', 0)
        end_pos = section.get('end_position', len(text))
        section_text = text[start_pos:end_pos]

        validation = validate_section_quality(section_text)
        quality_score = validation.get('quality_score', 0.5)

        if quality_score >= 0.7:
            quality_counts["high"] += 1
        elif quality_score >= 0.4:
            quality_counts["medium"] += 1
        else:
            quality_counts["low"] += 1

    return {
        "total_sections": len(sections),
        "high_quality": quality_counts["high"],
        "medium_quality": quality_counts["medium"],
        "low_quality": quality_counts["low"]
    }


def _generate_actionable_insights(classified_issues: List[Dict[str, Any]],
                                recommendations: List[Any],
                                sections_analysis: Dict[str, Any]) -> List[str]:
    """Generate actionable insights from analysis."""
    insights = []

    # Critical issues insights
    critical_issues = [issue for issue in classified_issues
                      if issue.get('severity', '').lower() == 'critical']
    if critical_issues:
        insights.append(f"URGENT: {len(critical_issues)} critical issues require immediate attention")

    # High-priority recommendations
    high_priority_recs = [rec for rec in recommendations
                         if hasattr(rec, 'priority') and rec.priority.value == 'high']
    if high_priority_recs:
        insights.append(f"Focus on {len(high_priority_recs)} high-priority fixes first")

    # Section quality insights
    total_sections = sections_analysis.get('total_sections', 0)
    high_quality = sections_analysis.get('high_quality', 0)
    low_quality = sections_analysis.get('low_quality', 0)

    if total_sections > 0:
        if high_quality / total_sections > 0.7:
            insights.append(f"Good news: {high_quality}/{total_sections} sections are high quality")
        elif low_quality / total_sections > 0.5:
            insights.append(f"Concern: {low_quality}/{total_sections} sections need improvement")

    # Encoding issues
    encoding_issues = [issue for issue in classified_issues
                      if 'encoding' in issue.get('type', '').lower()]
    if encoding_issues:
        insights.append("Character encoding issues detected - consider reprocessing with UTF-8")

    # Structure issues
    structure_issues = [issue for issue in classified_issues
                       if 'structure' in issue.get('type', '').lower()]
    if structure_issues:
        insights.append("Document structure not fully preserved - may impact navigation")

    return insights[:5]  # Return top 5 insights


def _map_position_to_section(position: int, sections: List[Dict[str, Any]]) -> str:
    """Map character position to section name."""
    if not isinstance(sections, list):
        return "Unknown section"

    for section in sections:
        start_pos = section.get('start_position', 0)
        end_pos = section.get('end_position', float('inf'))

        if start_pos <= position < end_pos:
            return section.get('title', 'Unnamed section')

    return "Unknown section"


def _estimate_text_completeness(text: str, metadata: Dict[str, Any]) -> float:
    """Estimate completeness of extracted text."""
    if not text:
        return 0.0

    file_size = metadata.get('file_size', 0)
    if file_size == 0:
        return 0.8  # Default assumption if no file size

    # Rough estimate: expect ~1 char per 3 bytes for typical documents
    expected_chars = file_size / 3
    actual_chars = len(text)

    completeness_ratio = actual_chars / expected_chars

    # Cap at 1.0 and apply reasonable bounds
    return min(1.0, max(0.1, completeness_ratio))


def _estimate_structure_preservation(structure_data: Dict[str, Any]) -> float:
    """Estimate how well document structure was preserved."""
    if not structure_data:
        return 0.3  # Low score if no structure analysis

    sections_found = len(structure_data.get('sections_detected', []))
    headers_found = len(structure_data.get('headers_detected', []))

    # Simple heuristic based on structure elements found
    if sections_found >= 3 and headers_found >= 3:
        return 0.8
    elif sections_found >= 1 or headers_found >= 1:
        return 0.6
    else:
        return 0.4


def _estimate_formatting_retention(file_type: str, text: str) -> float:
    """Estimate how well formatting was retained."""
    # Text files have minimal formatting
    if file_type.lower() == 'txt':
        return 0.9

    # PDFs often lose formatting in text extraction
    elif file_type.lower() == 'pdf':
        # Look for formatting indicators in text
        has_structure = bool(re.search(r'\n\s*[A-Z][A-Z\s]+\s*\n', text))  # Headers
        has_lists = bool(re.search(r'\n\s*[-*•]\s+', text))  # Lists

        if has_structure and has_lists:
            return 0.6
        elif has_structure or has_lists:
            return 0.4
        else:
            return 0.2

    # Office documents have variable formatting retention
    elif file_type.lower() in ['docx', 'doc']:
        return 0.5

    # HTML preserves some structure
    elif file_type.lower() in ['html', 'htm']:
        return 0.7

    return 0.5  # Default


def main():
    """Main CLI entry point."""
    parser = argparse.ArgumentParser(
        description='Extract text from various document formats',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Supported formats:
  PDF: .pdf
  Office: .docx, .xlsx, .pptx
  Text: .txt, .csv, .rtf
  Web: .html, .htm, .xml

Examples:
  python main_extractor.py --input document.pdf
  python main_extractor.py --input document.pdf --output result.json
  python main_extractor.py --input document.pdf --detailed-analysis
  python main_extractor.py --input document.pdf --structure-analysis
  python main_extractor.py --input /path/to/file.docx --output /path/to/output.json --detailed-analysis
  python main_extractor.py --input report.pdf --structure-analysis --verbose
        """
    )

    parser.add_argument(
        '--input',
        required=True,
        help='Path to input document'
    )

    parser.add_argument(
        '--output',
        help='Path to output JSON file (optional, prints to stdout if not specified)'
    )

    parser.add_argument(
        '--verbose',
        action='store_true',
        help='Enable verbose output'
    )

    parser.add_argument(
        '--detailed-analysis',
        action='store_true',
        help='Enable detailed analysis with metadata extraction, language detection, and comprehensive reporting'
    )

    parser.add_argument(
        '--structure-analysis',
        action='store_true',
        help='Enable advanced structure analysis (includes detailed analysis plus semantic parsing and hierarchy mapping)'
    )

    parser.add_argument(
        '--failure-analysis',
        action='store_true',
        help='Enable comprehensive failure analysis with actionable recommendations and quality insights'
    )

    args = parser.parse_args()

    if args.verbose:
        print(f"Processing file: {args.input}")
        print(f"Detected file type: {detect_file_type(args.input)}")

    # Extract document
    result = extract_document(args.input,
                            detailed_analysis=args.detailed_analysis,
                            structure_analysis=args.structure_analysis,
                            failure_analysis=args.failure_analysis)

    if args.verbose and (args.detailed_analysis or args.structure_analysis):
        print(f"Advanced document type detection: {result.get('document_type_info', {}).get('primary_type', 'N/A')}")
        print(f"Detected language: {result.get('language_info', {}).get('language', 'N/A')}")
        print(f"Language confidence: {result.get('language_info', {}).get('confidence', 0):.2f}")

        if args.structure_analysis and result.get('structure_analysis'):
            struct_analysis = result['structure_analysis']
            print(f"Document sections detected: {struct_analysis.get('document_hierarchy', {}).get('total_sections', 0)}")
            print(f"Structure quality score: {struct_analysis.get('structural_quality', {}).get('overall_score', 0):.2f}")
            print(f"Hierarchy max depth: {struct_analysis.get('document_hierarchy', {}).get('max_depth', 0)}")

    # Format output
    output_json = json.dumps(result, indent=2, ensure_ascii=False)

    # Output result
    if args.output:
        try:
            with open(args.output, 'w', encoding='utf-8') as f:
                f.write(output_json)
            if args.verbose:
                print(f"Results saved to: {args.output}")
        except Exception as e:
            print(f"Error writing output file: {e}", file=sys.stderr)
            sys.exit(1)
    else:
        print(output_json)

    # Exit with appropriate code
    sys.exit(0 if result['success'] else 1)


if __name__ == "__main__":
    main()