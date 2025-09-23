"""
Structured reporting for document extraction quality analysis.
"""

import json
from datetime import datetime
from typing import Dict, Any, Optional, List


class QualityReporter:
    """Generate structured reports for document extraction quality."""

    def generate_report(
        self,
        extraction_result: Dict[str, Any],
        metadata: Dict[str, Any],
        quality_data: Dict[str, Any],
        language_info: Optional[Dict[str, Any]] = None,
        doc_type_info: Optional[Dict[str, Any]] = None
    ) -> Dict[str, Any]:
        """
        Generate comprehensive extraction report.

        Args:
            extraction_result: Results from document extraction
            metadata: Document metadata
            quality_data: Quality analysis results
            language_info: Language detection results
            doc_type_info: Document type detection results

        Returns:
            Structured report dictionary
        """
        report_timestamp = datetime.utcnow().isoformat()

        # Base report structure
        report = {
            "report_info": {
                "generated_at": report_timestamp,
                "report_version": "1.0",
                "analysis_type": "detailed" if "detailed_analysis" in quality_data else "basic"
            },
            "document_info": self._extract_document_info(extraction_result, metadata),
            "extraction_summary": self._create_extraction_summary(extraction_result, quality_data),
            "quality_assessment": self._create_quality_assessment(quality_data),
            "technical_details": self._create_technical_details(metadata, language_info, doc_type_info)
        }

        # Add detailed analysis if available
        if "detailed_analysis" in quality_data:
            report["detailed_analysis"] = self._create_detailed_analysis_section(quality_data["detailed_analysis"])

        # Add recommendations
        report["recommendations"] = self._generate_recommendations(report)

        return report

    def _extract_document_info(self, extraction_result: Dict[str, Any], metadata: Dict[str, Any]) -> Dict[str, Any]:
        """Extract basic document information."""
        doc_info = {
            "filename": metadata.get("filename", "unknown"),
            "file_size": metadata.get("file_size", 0),
            "file_extension": metadata.get("file_extension", ""),
            "file_type": extraction_result.get("file_type", "unknown")
        }

        # Add creation/modification dates if available
        if "created_at" in metadata:
            doc_info["created_at"] = metadata["created_at"]
        if "modified_at" in metadata:
            doc_info["modified_at"] = metadata["modified_at"]

        # Add document-specific info
        if "page_count" in metadata:
            doc_info["page_count"] = metadata["page_count"]
        elif "sheet_count" in metadata:
            doc_info["sheet_count"] = metadata["sheet_count"]
        elif "slide_count" in metadata:
            doc_info["slide_count"] = metadata["slide_count"]

        # Add title/author if available
        if "title" in metadata and metadata["title"]:
            doc_info["title"] = metadata["title"]
        if "author" in metadata and metadata["author"]:
            doc_info["author"] = metadata["author"]

        return doc_info

    def _create_extraction_summary(self, extraction_result: Dict[str, Any], quality_data: Dict[str, Any]) -> Dict[str, Any]:
        """Create extraction summary section."""
        summary = {
            "success": extraction_result.get("success", False),
            "extracted_text_length": len(extraction_result.get("extracted_text", "")),
            "word_count": len(extraction_result.get("extracted_text", "").split()) if extraction_result.get("extracted_text") else 0,
            "quality_rating": quality_data.get("quality_rating", "unknown"),
            "extraction_success_rate": quality_data.get("extraction_success_rate", 0)
        }

        # Add processing time if available
        if "processing_time" in extraction_result:
            summary["processing_time_seconds"] = extraction_result["processing_time"]

        return summary

    def _create_quality_assessment(self, quality_data: Dict[str, Any]) -> Dict[str, Any]:
        """Create quality assessment section."""
        assessment = {
            "overall_rating": quality_data.get("quality_rating", "unknown"),
            "extraction_success_rate": quality_data.get("extraction_success_rate", 0),
            "quality_indicators": []
        }

        # Determine quality indicators based on rating
        rating = quality_data.get("quality_rating", "poor")

        if rating == "excellent":
            assessment["quality_indicators"].extend([
                "High-quality text extraction",
                "Well-preserved document structure",
                "Minimal extraction artifacts"
            ])
        elif rating == "good":
            assessment["quality_indicators"].extend([
                "Acceptable text extraction quality",
                "Some structure preserved",
                "Minor extraction issues"
            ])
        else:
            assessment["quality_indicators"].extend([
                "Low extraction quality detected",
                "Possible structure loss",
                "May require manual review"
            ])

        return assessment

    def _create_technical_details(
        self,
        metadata: Dict[str, Any],
        language_info: Optional[Dict[str, Any]],
        doc_type_info: Optional[Dict[str, Any]]
    ) -> Dict[str, Any]:
        """Create technical details section."""
        details = {
            "file_metadata": self._filter_metadata_for_report(metadata),
            "encoding_info": {},
            "structure_info": {}
        }

        # Add language detection results
        if language_info:
            details["language_detection"] = {
                "detected_language": language_info.get("language", "unknown"),
                "confidence": language_info.get("confidence", 0),
                "detection_method": language_info.get("method", "unknown")
            }

        # Add document type detection results
        if doc_type_info:
            details["document_type_detection"] = {
                "primary_type": doc_type_info.get("primary_type", "unknown"),
                "sub_types": doc_type_info.get("sub_types", []),
                "detection_confidence": doc_type_info.get("confidence", 0),
                "mime_type": doc_type_info.get("mime_type", "")
            }

        # Add encoding info if available in metadata
        if "encoding" in metadata:
            details["encoding_info"]["text_encoding"] = metadata["encoding"]

        return details

    def _create_detailed_analysis_section(self, detailed_analysis: Dict[str, Any]) -> Dict[str, Any]:
        """Create detailed analysis section from quality analyzer."""
        analysis_section = {}

        # Structural elements
        if "structural_elements" in detailed_analysis:
            structure = detailed_analysis["structural_elements"]
            analysis_section["document_structure"] = {
                "paragraphs": structure.get("paragraphs", 0),
                "sentences": structure.get("sentences", 0),
                "headers": structure.get("headers", 0),
                "lists": structure.get("lists", {}),
                "tables_detected": structure.get("tables", {}).get("detected", False),
                "estimated_table_rows": structure.get("tables", {}).get("estimated_rows", 0)
            }

        # Content metrics
        if "content_metrics" in detailed_analysis:
            metrics = detailed_analysis["content_metrics"]
            analysis_section["content_analysis"] = {}

            if "character_distribution" in metrics:
                char_dist = metrics["character_distribution"]
                analysis_section["content_analysis"]["character_composition"] = {
                    "alphabetic_percentage": round(char_dist.get("alphabetic_ratio", 0) * 100, 1),
                    "numeric_percentage": round(char_dist.get("numeric_ratio", 0) * 100, 1),
                    "whitespace_percentage": round(char_dist.get("whitespace_ratio", 0) * 100, 1)
                }

        # Encoding issues
        if "encoding_issues" in detailed_analysis:
            issues = detailed_analysis["encoding_issues"]
            if issues:
                analysis_section["quality_issues"] = [
                    {
                        "type": issue.get("type", "unknown"),
                        "severity": issue.get("severity", "unknown"),
                        "description": issue.get("description", "No description")
                    }
                    for issue in issues
                ]

        return analysis_section

    def _filter_metadata_for_report(self, metadata: Dict[str, Any]) -> Dict[str, Any]:
        """Filter metadata to include only relevant information for reports."""
        relevant_fields = [
            "filename", "file_size", "file_extension", "created_at", "modified_at",
            "title", "author", "subject", "page_count", "sheet_count", "slide_count"
        ]

        filtered = {}
        for field in relevant_fields:
            if field in metadata and metadata[field] is not None:
                if field in ["title", "author", "subject"]:
                    if metadata[field]:  # Only include if not empty
                        filtered[field] = metadata[field]
                else:
                    filtered[field] = metadata[field]

        return filtered

    def _generate_recommendations(self, report: Dict[str, Any]) -> List[Dict[str, str]]:
        """Generate recommendations based on the analysis."""
        recommendations = []

        # Get key metrics
        quality_rating = report.get("quality_assessment", {}).get("overall_rating", "poor")
        success_rate = report.get("extraction_summary", {}).get("extraction_success_rate", 0)

        # Quality-based recommendations
        if quality_rating == "poor":
            recommendations.append({
                "category": "quality",
                "priority": "high",
                "recommendation": "Consider manual review of extracted content due to low quality score",
                "reason": "Poor extraction quality detected"
            })

            if success_rate < 50:
                recommendations.append({
                    "category": "extraction",
                    "priority": "high",
                    "recommendation": "Try alternative extraction methods or tools for this document type",
                    "reason": f"Low extraction success rate ({success_rate:.1f}%)"
                })

        elif quality_rating == "good":
            recommendations.append({
                "category": "quality",
                "priority": "medium",
                "recommendation": "Review extracted content for accuracy, especially complex structures",
                "reason": "Good quality with potential minor issues"
            })

        # Default recommendation if no issues found
        if not recommendations and quality_rating == "excellent":
            recommendations.append({
                "category": "quality",
                "priority": "low",
                "recommendation": "Extracted content appears to be of high quality and ready for use",
                "reason": "Excellent extraction quality achieved"
            })

        return recommendations

    def export_report_json(self, report: Dict[str, Any], output_path: str) -> bool:
        """Export report to JSON file."""
        try:
            with open(output_path, 'w', encoding='utf-8') as f:
                json.dump(report, f, indent=2, ensure_ascii=False)
            return True
        except Exception:
            return False

    def export_report_markdown(self, report: Dict[str, Any], output_path: str) -> bool:
        """Export report to Markdown format."""
        try:
            markdown_content = self._convert_report_to_markdown(report)
            with open(output_path, 'w', encoding='utf-8') as f:
                f.write(markdown_content)
            return True
        except Exception:
            return False

    def _convert_report_to_markdown(self, report: Dict[str, Any]) -> str:
        """Convert report dictionary to Markdown format."""
        lines = []

        # Header
        lines.append("# Document Extraction Quality Report")
        lines.append("")
        lines.append(f"**Generated:** {report.get('report_info', {}).get('generated_at', 'Unknown')}")
        lines.append("")

        # Document Information
        doc_info = report.get("document_info", {})
        lines.append("## Document Information")
        lines.append("")
        lines.append(f"- **Filename:** {doc_info.get('filename', 'Unknown')}")
        lines.append(f"- **File Type:** {doc_info.get('file_type', 'Unknown')}")
        lines.append(f"- **File Size:** {doc_info.get('file_size', 0):,} bytes")

        if "page_count" in doc_info:
            lines.append(f"- **Pages:** {doc_info['page_count']}")

        if "title" in doc_info:
            lines.append(f"- **Title:** {doc_info['title']}")

        lines.append("")

        # Extraction Summary
        summary = report.get("extraction_summary", {})
        lines.append("## Extraction Summary")
        lines.append("")
        lines.append(f"- **Success:** {'✅ Yes' if summary.get('success') else '❌ No'}")
        lines.append(f"- **Quality Rating:** {summary.get('quality_rating', 'Unknown').title()}")
        lines.append(f"- **Success Rate:** {summary.get('extraction_success_rate', 0):.1f}%")
        lines.append(f"- **Text Length:** {summary.get('extracted_text_length', 0):,} characters")
        lines.append("")

        return "\n".join(lines)