"""
Document hierarchy mapping and navigation tree generation.
"""

import re
from typing import Dict, Any, List, Optional, Tuple
from collections import defaultdict


class HierarchyMapper:
    """Map document hierarchy and create navigation structures."""

    def __init__(self):
        self.numbering_systems = self._init_numbering_systems()

    def build_document_tree(self, sections: List[Dict[str, Any]], headers: List[Dict[str, Any]]) -> Dict[str, Any]:
        """
        Build hierarchical document tree.

        Args:
            sections: Document sections
            headers: Document headers

        Returns:
            Document tree structure
        """
        if not sections:
            return self._empty_tree()

        # Create hierarchy based on section levels
        tree = {
            "root": [],
            "metadata": {
                "total_sections": len(sections),
                "max_depth": 0,
                "section_types": {}
            }
        }

        # Build section hierarchy
        root_sections = self._build_section_tree(sections)
        tree["root"] = root_sections

        # Calculate metadata
        tree["metadata"]["max_depth"] = self._calculate_tree_depth(root_sections)
        tree["metadata"]["section_types"] = self._analyze_section_types(sections)

        # Add navigation aids
        tree["navigation"] = {
            "breadcrumb_paths": self._generate_breadcrumb_paths(root_sections),
            "quick_links": self._generate_quick_links(sections),
            "section_map": self._create_section_map(root_sections)
        }

        return tree

    def generate_toc(self, hierarchy: Dict[str, Any]) -> List[Dict[str, Any]]:
        """
        Generate table of contents from hierarchy.

        Args:
            hierarchy: Document hierarchy structure

        Returns:
            Table of contents entries
        """
        toc_entries = []

        def extract_toc_recursive(sections: List[Dict[str, Any]], level: int = 1):
            for section in sections:
                # Create TOC entry
                entry = {
                    "title": section.get("title", "Untitled Section"),
                    "level": level,
                    "type": section.get("type", "section"),
                    "position": section.get("start_position", 0),
                    "word_count": section.get("word_count", 0),
                    "section_id": self._generate_section_id(section.get("title", "")),
                    "page_estimate": self._estimate_page_number(section.get("start_position", 0)),
                    "confidence": section.get("confidence", 0.8)
                }

                # Add numbering if detected
                numbering = section.get("number") or self._extract_section_numbering(section.get("title", ""))
                if numbering:
                    entry["number"] = numbering

                toc_entries.append(entry)

                # Process subsections recursively
                subsections = section.get("subsections", [])
                if subsections:
                    extract_toc_recursive(subsections, level + 1)

        # Extract from hierarchy root
        if "root" in hierarchy:
            extract_toc_recursive(hierarchy["root"])
        elif "sections" in hierarchy:
            # Fallback for different hierarchy format
            extract_toc_recursive(hierarchy["sections"])
        elif isinstance(hierarchy, list):
            # Direct list of sections
            extract_toc_recursive(hierarchy)

        # Add TOC statistics
        toc_stats = self._calculate_toc_statistics(toc_entries)

        return {
            "entries": toc_entries,
            "statistics": toc_stats,
            "generated_at": self._get_timestamp(),
            "total_entries": len(toc_entries)
        }

    def validate_structure(self, hierarchy: Dict[str, Any]) -> Dict[str, Any]:
        """
        Validate document structure consistency.

        Args:
            hierarchy: Document hierarchy to validate

        Returns:
            Validation report
        """
        validation = {
            "is_valid": True,
            "issues": [],
            "warnings": [],
            "suggestions": [],
            "quality_score": 1.0
        }

        sections = hierarchy.get("root", []) or hierarchy.get("sections", [])

        if not sections:
            validation["is_valid"] = False
            validation["issues"].append("No sections found in document hierarchy")
            validation["quality_score"] = 0.0
            return validation

        # Check hierarchy consistency
        self._validate_level_consistency(sections, validation)

        # Check numbering consistency
        self._validate_numbering_consistency(sections, validation)

        # Check section balance
        self._validate_section_balance(sections, validation)

        # Check content distribution
        self._validate_content_distribution(sections, validation)

        # Calculate final quality score
        validation["quality_score"] = self._calculate_validation_score(validation)

        return validation

    def _init_numbering_systems(self) -> Dict[str, Dict[str, Any]]:
        """Initialize different numbering system patterns."""
        return {
            "decimal": {
                "pattern": re.compile(r'^(\d+(?:\.\d+)*)\s*\.?\s*(.+)'),
                "description": "Decimal numbering (1, 1.1, 1.1.1)",
                "level_calculator": lambda num: len(num.split('.'))
            },
            "roman": {
                "pattern": re.compile(r'^([IVX]+)\.\s*(.+)', re.IGNORECASE),
                "description": "Roman numerals (I, II, III)",
                "level_calculator": lambda num: 1
            },
            "letter": {
                "pattern": re.compile(r'^([A-Za-z])[.)]\s*(.+)'),
                "description": "Letter numbering (A, B, C)",
                "level_calculator": lambda num: 1
            },
            "mixed": {
                "pattern": re.compile(r'^([A-Z]\d+|\d+[A-Z]|\d+\.\d+[A-Za-z])\s*[\.\)]\s*(.+)'),
                "description": "Mixed alphanumeric (A1, 1A, 1.1a)",
                "level_calculator": lambda num: 2
            }
        }

    def _build_section_tree(self, sections: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Build hierarchical tree from flat section list."""
        if not sections:
            return []

        # Sort sections by position
        sorted_sections = sorted(sections, key=lambda x: x.get("start_position", 0))

        # Build tree using level-based hierarchy
        tree = []
        section_stack = []

        for section in sorted_sections:
            level = section.get("level", 1)

            # Create enhanced section node
            section_node = {
                "level": level,
                "title": section.get("title", ""),
                "number": section.get("number"),
                "start_position": section.get("start_position", 0),
                "end_position": section.get("end_position", 0),
                "word_count": section.get("word_count", 0),
                "type": section.get("type", "section"),
                "content_preview": section.get("content_preview", ""),
                "subsections": [],
                "parent_id": None,
                "depth": level,
                "section_id": self._generate_section_id(section.get("title", "")),
                "navigation_hint": self._generate_navigation_hint(section)
            }

            # Pop sections from stack that are at same or deeper level
            while section_stack and section_stack[-1]["depth"] >= level:
                section_stack.pop()

            # Set parent relationship
            if section_stack:
                parent = section_stack[-1]
                section_node["parent_id"] = parent["section_id"]
                parent["subsections"].append(section_node)
            else:
                tree.append(section_node)

            section_stack.append(section_node)

        return tree

    def _calculate_tree_depth(self, sections: List[Dict[str, Any]]) -> int:
        """Calculate maximum depth of section tree."""
        if not sections:
            return 0

        max_depth = 1
        for section in sections:
            if section.get("subsections"):
                subsection_depth = 1 + self._calculate_tree_depth(section["subsections"])
                max_depth = max(max_depth, subsection_depth)

        return max_depth

    def _analyze_section_types(self, sections: List[Dict[str, Any]]) -> Dict[str, int]:
        """Analyze distribution of section types."""
        type_counts = defaultdict(int)

        for section in sections:
            section_type = section.get("type", "unknown")
            type_counts[section_type] += 1

        return dict(type_counts)

    def _generate_breadcrumb_paths(self, sections: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Generate breadcrumb navigation paths for all sections."""
        breadcrumbs = []

        def extract_paths(sections_list: List[Dict[str, Any]], path: List[str] = None):
            if path is None:
                path = []

            for section in sections_list:
                current_path = path + [section.get("title", "Untitled")]

                breadcrumbs.append({
                    "section_id": section.get("section_id"),
                    "path": current_path,
                    "level": len(current_path),
                    "position": section.get("start_position", 0)
                })

                # Process subsections
                if section.get("subsections"):
                    extract_paths(section["subsections"], current_path)

        extract_paths(sections)
        return breadcrumbs

    def _generate_quick_links(self, sections: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Generate quick navigation links for important sections."""
        quick_links = []

        # Prioritize certain section types
        priority_types = ["introduction", "conclusion", "summary", "references"]

        for section in sections:
            section_type = section.get("type", "")
            title = section.get("title", "")

            # Add high-priority sections
            if section_type in priority_types:
                quick_links.append({
                    "title": title,
                    "type": section_type,
                    "position": section.get("start_position", 0),
                    "priority": "high"
                })
            # Add first-level sections
            elif section.get("level", 1) == 1:
                quick_links.append({
                    "title": title,
                    "type": section_type,
                    "position": section.get("start_position", 0),
                    "priority": "medium"
                })

        # Sort by priority and position
        quick_links.sort(key=lambda x: (
            {"high": 0, "medium": 1, "low": 2}.get(x["priority"], 3),
            x["position"]
        ))

        return quick_links[:10]  # Limit to top 10

    def _create_section_map(self, sections: List[Dict[str, Any]]) -> Dict[str, Any]:
        """Create a flat map of all sections for quick lookup."""
        section_map = {}

        def map_sections(sections_list: List[Dict[str, Any]]):
            for section in sections_list:
                section_id = section.get("section_id")
                if section_id:
                    section_map[section_id] = {
                        "title": section.get("title"),
                        "number": section.get("number"),
                        "type": section.get("type"),
                        "level": section.get("level"),
                        "position": section.get("start_position"),
                        "word_count": section.get("word_count"),
                        "parent_id": section.get("parent_id")
                    }

                # Map subsections
                if section.get("subsections"):
                    map_sections(section["subsections"])

        map_sections(sections)
        return section_map

    def _generate_section_id(self, title: str) -> str:
        """Generate unique ID for section."""
        if not title:
            return f"section_{id(title)}"

        # Clean title for ID
        clean_title = re.sub(r'[^\w\s-]', '', title.lower())
        clean_title = re.sub(r'[-\s]+', '-', clean_title)
        return f"section-{clean_title[:50]}"  # Limit length

    def _generate_navigation_hint(self, section: Dict[str, Any]) -> str:
        """Generate navigation hint for section."""
        section_type = section.get("type", "")
        level = section.get("level", 1)
        word_count = section.get("word_count", 0)

        if section_type == "introduction":
            return "Document introduction"
        elif section_type == "conclusion":
            return "Document conclusion"
        elif level == 1:
            return "Main section"
        elif word_count > 500:
            return "Detailed section"
        else:
            return f"Level {level} section"

    def _extract_section_numbering(self, title: str) -> Optional[str]:
        """Extract numbering from section title."""
        for system_name, system in self.numbering_systems.items():
            match = system["pattern"].match(title.strip())
            if match:
                return match.group(1)
        return None

    def _estimate_page_number(self, position: int) -> int:
        """Estimate page number based on character position."""
        # Rough estimate: 2000 characters per page
        return max(1, (position // 2000) + 1)

    def _calculate_toc_statistics(self, toc_entries: List[Dict[str, Any]]) -> Dict[str, Any]:
        """Calculate table of contents statistics."""
        if not toc_entries:
            return {}

        levels = [entry["level"] for entry in toc_entries]
        word_counts = [entry.get("word_count", 0) for entry in toc_entries]

        return {
            "total_entries": len(toc_entries),
            "max_level": max(levels),
            "avg_level": sum(levels) / len(levels),
            "total_words": sum(word_counts),
            "avg_words_per_section": sum(word_counts) / len(word_counts) if word_counts else 0,
            "sections_by_level": {str(level): levels.count(level) for level in set(levels)}
        }

    def _get_timestamp(self) -> str:
        """Get current timestamp."""
        from datetime import datetime
        return datetime.utcnow().isoformat()

    def _validate_level_consistency(self, sections: List[Dict[str, Any]], validation: Dict[str, Any]):
        """Validate level numbering consistency."""
        def check_levels(sections_list: List[Dict[str, Any]], expected_level: int = 1):
            for section in sections_list:
                actual_level = section.get("level", 1)

                if actual_level > expected_level + 1:
                    validation["warnings"].append(
                        f"Section '{section.get('title', 'Unnamed')}' skips from level {expected_level} to {actual_level}"
                    )

                # Check subsections
                if section.get("subsections"):
                    check_levels(section["subsections"], actual_level)

        check_levels(sections)

    def _validate_numbering_consistency(self, sections: List[Dict[str, Any]], validation: Dict[str, Any]):
        """Validate numbering system consistency."""
        numbering_systems_used = set()

        for section in sections:
            title = section.get("title", "")
            numbering = self._extract_section_numbering(title)

            if numbering:
                for system_name, system in self.numbering_systems.items():
                    if system["pattern"].match(title):
                        numbering_systems_used.add(system_name)
                        break

        if len(numbering_systems_used) > 2:
            validation["warnings"].append(
                f"Multiple numbering systems detected: {', '.join(numbering_systems_used)}"
            )

    def _validate_section_balance(self, sections: List[Dict[str, Any]], validation: Dict[str, Any]):
        """Validate section size balance."""
        word_counts = [section.get("word_count", 0) for section in sections if section.get("word_count", 0) > 0]

        if not word_counts:
            return

        avg_words = sum(word_counts) / len(word_counts)

        # Check for extremely imbalanced sections
        for section in sections:
            word_count = section.get("word_count", 0)
            if word_count > 0:
                ratio = word_count / avg_words

                if ratio > 5:  # Section is 5x larger than average
                    validation["suggestions"].append(
                        f"Section '{section.get('title', 'Unnamed')}' is significantly longer than average"
                    )
                elif ratio < 0.1:  # Section is 10x smaller than average
                    validation["suggestions"].append(
                        f"Section '{section.get('title', 'Unnamed')}' is significantly shorter than average"
                    )

    def _validate_content_distribution(self, sections: List[Dict[str, Any]], validation: Dict[str, Any]):
        """Validate content distribution across sections."""
        total_words = sum(section.get("word_count", 0) for section in sections)

        if total_words < 100:
            validation["warnings"].append("Document appears to have very little content")

        # Check for sections with no content
        empty_sections = [section for section in sections if section.get("word_count", 0) == 0]
        if empty_sections:
            validation["warnings"].append(f"Found {len(empty_sections)} sections with no content")

    def _calculate_validation_score(self, validation: Dict[str, Any]) -> float:
        """Calculate overall validation quality score."""
        base_score = 1.0

        # Deduct for issues
        base_score -= len(validation["issues"]) * 0.2

        # Deduct for warnings
        base_score -= len(validation["warnings"]) * 0.1

        # Small deduction for suggestions
        base_score -= len(validation["suggestions"]) * 0.05

        return max(0.0, min(1.0, base_score))

    def _empty_tree(self) -> Dict[str, Any]:
        """Return empty tree structure."""
        return {
            "root": [],
            "metadata": {
                "total_sections": 0,
                "max_depth": 0,
                "section_types": {}
            },
            "navigation": {
                "breadcrumb_paths": [],
                "quick_links": [],
                "section_map": {}
            }
        }