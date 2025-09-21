#!/usr/bin/env python3
"""
Quality Reporter

Consolidates extraction results and generates comprehensive quality reports
with metrics and recommendations.
"""

import json
from datetime import datetime
from typing import Dict, Any, List
from pathlib import Path


class QualityReporter:
    def __init__(self):
        self.report_version = "1.0"

    def generate_consolidated_report(self, extraction_results: List[Dict[str, Any]],
                                   batch_info: Dict[str, Any] = None) -> Dict[str, Any]:
        """Generate consolidated report from multiple extraction results"""

        if not extraction_results:
            return self._empty_report("No extraction results provided")

        # Initialize consolidated metrics
        consolidated = {
            "report_metadata": {
                "version": self.report_version,
                "generated_at": datetime.now().isoformat(),
                "total_files": len(extraction_results),
                "batch_info": batch_info or {}
            },
            "overall_stats": {
                "total_files_processed": len(extraction_results),
                "successful_extractions": 0,
                "failed_extractions": 0,
                "total_elements": 0,
                "total_extracted_elements": 0,
                "average_extraction_percentage": 0.0
            },
            "file_type_breakdown": {},
            "quality_distribution": {
                "GOOD": 0,
                "FAIR": 0,
                "POOR": 0,
                "ERROR": 0
            },
            "common_issues": {},
            "top_recommendations": {},
            "detailed_results": [],
            "summary_insights": [],
            "processing_recommendations": []
        }

        # Process each extraction result
        extraction_percentages = []
        all_issues = []
        all_recommendations = []

        for i, result in enumerate(extraction_results):
            try:
                # Basic validation
                if not isinstance(result, dict):
                    continue

                # Process success/failure
                if result.get("success", False):
                    consolidated["overall_stats"]["successful_extractions"] += 1
                else:
                    consolidated["overall_stats"]["failed_extractions"] += 1

                # Extract metrics
                stats = result.get("extraction_stats", {})
                file_type = result.get("file_type", "unknown")
                quality_report = result.get("quality_report", {})

                # Accumulate stats
                consolidated["overall_stats"]["total_elements"] += stats.get("total_elements", 0)
                consolidated["overall_stats"]["total_extracted_elements"] += stats.get("extracted_elements", 0)

                extraction_pct = stats.get("extraction_percentage", 0.0)
                if extraction_pct > 0:
                    extraction_percentages.append(extraction_pct)

                # File type breakdown
                if file_type not in consolidated["file_type_breakdown"]:
                    consolidated["file_type_breakdown"][file_type] = {
                        "count": 0,
                        "successful": 0,
                        "failed": 0,
                        "avg_extraction_percentage": 0.0,
                        "total_elements": 0,
                        "extracted_elements": 0
                    }

                breakdown = consolidated["file_type_breakdown"][file_type]
                breakdown["count"] += 1
                breakdown["total_elements"] += stats.get("total_elements", 0)
                breakdown["extracted_elements"] += stats.get("extracted_elements", 0)

                if result.get("success", False):
                    breakdown["successful"] += 1
                else:
                    breakdown["failed"] += 1

                # Quality distribution
                quality_status = quality_report.get("status", "ERROR")
                if quality_status in consolidated["quality_distribution"]:
                    consolidated["quality_distribution"][quality_status] += 1

                # Collect issues and recommendations
                issues = quality_report.get("issues", [])
                recommendations = quality_report.get("recommendations", [])

                all_issues.extend(issues)
                all_recommendations.extend(recommendations)

                # Add to detailed results
                detailed_result = {
                    "index": i,
                    "file_type": file_type,
                    "success": result.get("success", False),
                    "extraction_percentage": extraction_pct,
                    "quality_status": quality_status,
                    "issues_count": len(issues),
                    "recommendations_count": len(recommendations),
                    "file_path": result.get("file_path", ""),
                    "processing_time": result.get("processing_time", 0),
                    "content_preview": self._generate_content_preview(result.get("content", {}))
                }
                consolidated["detailed_results"].append(detailed_result)

            except Exception as e:
                # Handle malformed results
                consolidated["detailed_results"].append({
                    "index": i,
                    "error": f"Failed to process result: {str(e)}",
                    "raw_result": result
                })

        # Calculate overall averages
        if extraction_percentages:
            consolidated["overall_stats"]["average_extraction_percentage"] = \
                round(sum(extraction_percentages) / len(extraction_percentages), 2)

        # Calculate file type averages
        for file_type, breakdown in consolidated["file_type_breakdown"].items():
            if breakdown["total_elements"] > 0:
                breakdown["avg_extraction_percentage"] = \
                    round((breakdown["extracted_elements"] / breakdown["total_elements"]) * 100, 2)

        # Analyze common issues
        consolidated["common_issues"] = self._analyze_common_patterns(all_issues)
        consolidated["top_recommendations"] = self._analyze_common_patterns(all_recommendations)

        # Generate insights and recommendations
        consolidated["summary_insights"] = self._generate_insights(consolidated)
        consolidated["processing_recommendations"] = self._generate_processing_recommendations(consolidated)

        return consolidated

    def generate_single_file_report(self, extraction_result: Dict[str, Any],
                                   file_path: str = "") -> Dict[str, Any]:
        """Generate enhanced report for a single file extraction"""

        if not extraction_result:
            return self._empty_report("No extraction result provided")

        # Base report structure
        report = {
            "report_metadata": {
                "version": self.report_version,
                "generated_at": datetime.now().isoformat(),
                "file_path": file_path,
                "report_type": "single_file"
            },
            "extraction_result": extraction_result.copy(),
            "enhanced_analysis": {},
            "actionable_recommendations": [],
            "next_steps": []
        }

        try:
            # Enhanced analysis
            stats = extraction_result.get("extraction_stats", {})
            quality_report = extraction_result.get("quality_report", {})
            content = extraction_result.get("content", {})

            # Content analysis
            content_analysis = self._analyze_content(content, extraction_result.get("file_type", ""))
            report["enhanced_analysis"]["content_analysis"] = content_analysis

            # Performance analysis
            performance_analysis = self._analyze_performance(stats)
            report["enhanced_analysis"]["performance_analysis"] = performance_analysis

            # Quality analysis
            quality_analysis = self._analyze_quality(quality_report, stats)
            report["enhanced_analysis"]["quality_analysis"] = quality_analysis

            # Generate actionable recommendations
            report["actionable_recommendations"] = self._generate_actionable_recommendations(
                extraction_result, content_analysis, performance_analysis, quality_analysis
            )

            # Generate next steps
            report["next_steps"] = self._generate_next_steps(extraction_result, report["actionable_recommendations"])

        except Exception as e:
            report["enhanced_analysis"]["error"] = f"Failed to generate enhanced analysis: {str(e)}"

        return report

    def _analyze_content(self, content: Dict[str, Any], file_type: str) -> Dict[str, Any]:
        """Analyze extracted content"""
        analysis = {
            "content_type": file_type,
            "text_metrics": {},
            "structure_metrics": {},
            "richness_score": 0
        }

        try:
            # Text metrics
            text_content = ""
            if isinstance(content, dict):
                # Try to find text in various formats
                text_content = (content.get("text_content", "") or
                               content.get("text", "") or
                               str(content.get("content", "")))

            if text_content:
                words = text_content.split()
                analysis["text_metrics"] = {
                    "character_count": len(text_content),
                    "word_count": len(words),
                    "average_word_length": round(sum(len(word) for word in words) / len(words), 2) if words else 0,
                    "sentence_count": len([s for s in text_content.split('.') if s.strip()]),
                    "paragraph_count": len([p for p in text_content.split('\n\n') if p.strip()])
                }

                # Calculate richness score (0-100)
                richness_factors = []
                if analysis["text_metrics"]["word_count"] > 100:
                    richness_factors.append(20)
                elif analysis["text_metrics"]["word_count"] > 20:
                    richness_factors.append(10)

                if analysis["text_metrics"]["sentence_count"] > 5:
                    richness_factors.append(15)

                if analysis["text_metrics"]["paragraph_count"] > 2:
                    richness_factors.append(10)

                analysis["richness_score"] = sum(richness_factors)

            # Structure metrics (file-type specific)
            if file_type == "pdf":
                analysis["structure_metrics"] = self._analyze_pdf_structure(content)
            elif file_type == "html":
                analysis["structure_metrics"] = self._analyze_html_structure(content)
            elif file_type in ["docx", "xlsx", "pptx"]:
                analysis["structure_metrics"] = self._analyze_office_structure(content)

        except Exception as e:
            analysis["error"] = str(e)

        return analysis

    def _analyze_performance(self, stats: Dict[str, Any]) -> Dict[str, Any]:
        """Analyze extraction performance"""
        analysis = {
            "extraction_efficiency": "unknown",
            "completeness_score": 0,
            "performance_rating": "unknown"
        }

        try:
            extraction_pct = stats.get("extraction_percentage", 0.0)
            total_elements = stats.get("total_elements", 0)
            extracted_elements = stats.get("extracted_elements", 0)

            # Efficiency rating
            if extraction_pct >= 90:
                analysis["extraction_efficiency"] = "excellent"
            elif extraction_pct >= 75:
                analysis["extraction_efficiency"] = "good"
            elif extraction_pct >= 50:
                analysis["extraction_efficiency"] = "fair"
            else:
                analysis["extraction_efficiency"] = "poor"

            # Completeness score (0-100)
            analysis["completeness_score"] = round(extraction_pct, 1)

            # Overall performance rating
            if extraction_pct >= 85 and total_elements > 10:
                analysis["performance_rating"] = "high"
            elif extraction_pct >= 60 and total_elements > 5:
                analysis["performance_rating"] = "medium"
            else:
                analysis["performance_rating"] = "low"

        except Exception as e:
            analysis["error"] = str(e)

        return analysis

    def _analyze_quality(self, quality_report: Dict[str, Any], stats: Dict[str, Any]) -> Dict[str, Any]:
        """Analyze quality aspects"""
        analysis = {
            "quality_score": 0,
            "issue_severity": "unknown",
            "improvement_potential": "unknown",
            "reliability": "unknown"
        }

        try:
            status = quality_report.get("status", "UNKNOWN")
            issues = quality_report.get("issues", [])
            recommendations = quality_report.get("recommendations", [])

            # Quality score (0-100)
            base_score = {"GOOD": 85, "FAIR": 60, "POOR": 30, "ERROR": 0}.get(status, 0)

            # Adjust for issues
            issue_penalty = min(len(issues) * 5, 30)
            quality_score = max(base_score - issue_penalty, 0)

            # Bonus for high extraction percentage
            extraction_pct = stats.get("extraction_percentage", 0.0)
            if extraction_pct > 90:
                quality_score = min(quality_score + 10, 100)

            analysis["quality_score"] = quality_score

            # Issue severity
            if len(issues) == 0:
                analysis["issue_severity"] = "none"
            elif len(issues) <= 2:
                analysis["issue_severity"] = "low"
            elif len(issues) <= 5:
                analysis["issue_severity"] = "medium"
            else:
                analysis["issue_severity"] = "high"

            # Improvement potential
            if len(recommendations) > 3:
                analysis["improvement_potential"] = "high"
            elif len(recommendations) > 1:
                analysis["improvement_potential"] = "medium"
            else:
                analysis["improvement_potential"] = "low"

            # Reliability
            if status == "GOOD" and len(issues) <= 1:
                analysis["reliability"] = "high"
            elif status in ["GOOD", "FAIR"] and len(issues) <= 3:
                analysis["reliability"] = "medium"
            else:
                analysis["reliability"] = "low"

        except Exception as e:
            analysis["error"] = str(e)

        return analysis

    def _analyze_common_patterns(self, items: List[str]) -> Dict[str, int]:
        """Analyze common patterns in issues/recommendations"""
        from collections import Counter

        # Count exact matches
        counter = Counter(items)

        # Also look for keyword patterns
        keywords = {}
        for item in items:
            words = item.lower().split()
            for word in words:
                if len(word) > 3:  # Skip short words
                    keywords[word] = keywords.get(word, 0) + 1

        # Return top patterns
        top_exact = dict(counter.most_common(10))
        top_keywords = dict(sorted(keywords.items(), key=lambda x: x[1], reverse=True)[:10])

        return {
            "exact_matches": top_exact,
            "common_keywords": top_keywords
        }

    def _generate_content_preview(self, content: Dict[str, Any]) -> str:
        """Generate a preview of extracted content"""
        try:
            if isinstance(content, dict):
                text = (content.get("text_content", "") or
                       content.get("text", "") or
                       str(content.get("content", "")))

                if text:
                    # Return first 200 characters
                    preview = text[:200].strip()
                    if len(text) > 200:
                        preview += "..."
                    return preview

                # If no direct text, try to create summary from structure
                summary_parts = []
                if "title" in content:
                    summary_parts.append(f"Title: {content['title'][:50]}")
                if "headings" in content and content["headings"]:
                    summary_parts.append(f"Headings: {len(content['headings'])}")
                if "pages" in content and content["pages"]:
                    summary_parts.append(f"Pages: {len(content['pages'])}")

                return " | ".join(summary_parts) if summary_parts else "No preview available"

            return str(content)[:200]

        except:
            return "Preview generation failed"

    def _analyze_pdf_structure(self, content: Dict[str, Any]) -> Dict[str, Any]:
        """Analyze PDF-specific structure"""
        return {
            "page_count": len(content.get("pages", [])),
            "has_metadata": bool(content.get("metadata")),
            "has_bookmarks": bool(content.get("bookmarks")),
            "table_count": sum(len(page.get("tables", [])) for page in content.get("pages", [])),
            "image_count": sum(len(page.get("images", [])) for page in content.get("pages", []))
        }

    def _analyze_html_structure(self, content: Dict[str, Any]) -> Dict[str, Any]:
        """Analyze HTML-specific structure"""
        return {
            "has_title": bool(content.get("title")),
            "heading_count": len(content.get("headings", [])),
            "paragraph_count": len(content.get("paragraphs", [])),
            "link_count": len(content.get("links", [])),
            "image_count": len(content.get("images", [])),
            "table_count": len(content.get("tables", [])),
            "form_count": len(content.get("forms", []))
        }

    def _analyze_office_structure(self, content: Dict[str, Any]) -> Dict[str, Any]:
        """Analyze Office document structure"""
        return {
            "section_count": len(content.get("sections", [])),
            "has_styles": bool(content.get("styles")),
            "table_count": len(content.get("tables", [])),
            "image_count": len(content.get("images", [])),
            "has_metadata": bool(content.get("properties"))
        }

    def _generate_insights(self, consolidated: Dict[str, Any]) -> List[str]:
        """Generate summary insights from consolidated data"""
        insights = []

        try:
            stats = consolidated["overall_stats"]

            # Success rate insights
            success_rate = (stats["successful_extractions"] / stats["total_files_processed"]) * 100
            insights.append(f"Overall success rate: {success_rate:.1f}% ({stats['successful_extractions']}/{stats['total_files_processed']} files)")

            # Quality distribution insights
            quality_dist = consolidated["quality_distribution"]
            total_quality = sum(quality_dist.values())
            if total_quality > 0:
                good_pct = (quality_dist["GOOD"] / total_quality) * 100
                insights.append(f"Quality distribution: {good_pct:.1f}% good quality extractions")

            # File type insights
            file_types = consolidated["file_type_breakdown"]
            if file_types:
                best_type = max(file_types.items(), key=lambda x: x[1]["avg_extraction_percentage"])
                insights.append(f"Best performing file type: {best_type[0]} ({best_type[1]['avg_extraction_percentage']:.1f}% avg extraction)")

            # Common issues insight
            common_issues = consolidated["common_issues"].get("exact_matches", {})
            if common_issues:
                top_issue = max(common_issues.items(), key=lambda x: x[1])
                insights.append(f"Most common issue: '{top_issue[0]}' (affects {top_issue[1]} files)")

        except Exception as e:
            insights.append(f"Error generating insights: {str(e)}")

        return insights

    def _generate_processing_recommendations(self, consolidated: Dict[str, Any]) -> List[str]:
        """Generate processing recommendations from consolidated data"""
        recommendations = []

        try:
            stats = consolidated["overall_stats"]

            # Success rate recommendations
            success_rate = (stats["successful_extractions"] / stats["total_files_processed"]) * 100
            if success_rate < 80:
                recommendations.append("Consider preprocessing files to improve success rate")

            # Quality recommendations
            quality_dist = consolidated["quality_distribution"]
            poor_quality = quality_dist.get("POOR", 0) + quality_dist.get("ERROR", 0)
            if poor_quality > stats["total_files_processed"] * 0.3:
                recommendations.append("High number of poor quality extractions - review input file quality")

            # File type specific recommendations
            file_types = consolidated["file_type_breakdown"]
            for file_type, data in file_types.items():
                if data["avg_extraction_percentage"] < 50:
                    recommendations.append(f"Improve {file_type} extraction pipeline - currently at {data['avg_extraction_percentage']:.1f}%")

            # Common issues recommendations
            common_issues = consolidated["common_issues"].get("exact_matches", {})
            for issue, count in list(common_issues.items())[:3]:  # Top 3 issues
                if count >= 2:
                    recommendations.append(f"Address recurring issue: '{issue}' (affects {count} files)")

        except Exception as e:
            recommendations.append(f"Error generating recommendations: {str(e)}")

        return recommendations

    def _generate_actionable_recommendations(self, extraction_result: Dict[str, Any],
                                           content_analysis: Dict[str, Any],
                                           performance_analysis: Dict[str, Any],
                                           quality_analysis: Dict[str, Any]) -> List[Dict[str, Any]]:
        """Generate specific actionable recommendations"""
        recommendations = []

        try:
            # Performance-based recommendations
            if performance_analysis["extraction_efficiency"] == "poor":
                recommendations.append({
                    "category": "performance",
                    "priority": "high",
                    "action": "Improve extraction pipeline",
                    "description": "Low extraction efficiency detected",
                    "expected_impact": "Increase extraction percentage by 20-40%"
                })

            # Quality-based recommendations
            if quality_analysis["quality_score"] < 60:
                recommendations.append({
                    "category": "quality",
                    "priority": "high",
                    "action": "Review input quality",
                    "description": "Low quality score indicates issues with source material",
                    "expected_impact": "Improve overall extraction quality"
                })

            # Content-based recommendations
            if content_analysis.get("richness_score", 0) < 30:
                recommendations.append({
                    "category": "content",
                    "priority": "medium",
                    "action": "Verify content completeness",
                    "description": "Limited content detected - may indicate incomplete extraction",
                    "expected_impact": "Ensure all available content is captured"
                })

        except Exception as e:
            recommendations.append({
                "category": "error",
                "priority": "high",
                "action": "Debug recommendation system",
                "description": f"Error generating recommendations: {str(e)}",
                "expected_impact": "Fix system errors"
            })

        return recommendations

    def _generate_next_steps(self, extraction_result: Dict[str, Any],
                           actionable_recommendations: List[Dict[str, Any]]) -> List[str]:
        """Generate next steps based on results and recommendations"""
        next_steps = []

        try:
            if not extraction_result.get("success", False):
                next_steps.append("1. Fix extraction errors before proceeding")
                next_steps.append("2. Verify file format compatibility")
                return next_steps

            # Priority-based next steps
            high_priority_recs = [r for r in actionable_recommendations if r.get("priority") == "high"]
            if high_priority_recs:
                next_steps.append("1. Address high-priority issues:")
                for i, rec in enumerate(high_priority_recs, 2):
                    next_steps.append(f"   {i}. {rec['action']}")

            # Quality improvement steps
            quality_status = extraction_result.get("quality_report", {}).get("status", "")
            if quality_status in ["POOR", "FAIR"]:
                next_steps.append("2. Implement quality improvements:")
                next_steps.append("   - Review extraction parameters")
                next_steps.append("   - Consider preprocessing options")

            # General next steps
            next_steps.append("3. Monitor extraction metrics")
            next_steps.append("4. Review and implement recommendations")

        except Exception as e:
            next_steps.append(f"Error generating next steps: {str(e)}")

        return next_steps

    def _empty_report(self, reason: str) -> Dict[str, Any]:
        """Generate empty report structure"""
        return {
            "report_metadata": {
                "version": self.report_version,
                "generated_at": datetime.now().isoformat(),
                "error": reason
            },
            "overall_stats": {
                "total_files_processed": 0,
                "successful_extractions": 0,
                "failed_extractions": 0,
                "average_extraction_percentage": 0.0
            },
            "quality_distribution": {"GOOD": 0, "FAIR": 0, "POOR": 0, "ERROR": 0},
            "summary_insights": [f"Report generation failed: {reason}"],
            "processing_recommendations": ["Fix the underlying issue and retry"]
        }


if __name__ == "__main__":
    import sys

    if len(sys.argv) < 2:
        print("Usage: python reporter.py <results_file.json> [output_file.json]")
        sys.exit(1)

    results_file = sys.argv[1]
    output_file = sys.argv[2] if len(sys.argv) > 2 else None

    try:
        with open(results_file, 'r', encoding='utf-8') as f:
            results = json.load(f)

        reporter = QualityReporter()

        # Handle both single results and lists of results
        if isinstance(results, list):
            report = reporter.generate_consolidated_report(results)
        else:
            report = reporter.generate_single_file_report(results, results_file)

        if output_file:
            with open(output_file, 'w', encoding='utf-8') as f:
                json.dump(report, f, indent=2, ensure_ascii=False)
            print(f"Report saved to {output_file}")
        else:
            print(json.dumps(report, indent=2, ensure_ascii=False))

    except Exception as e:
        print(f"Error generating report: {str(e)}")
        sys.exit(1)