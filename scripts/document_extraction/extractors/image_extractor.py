#!/usr/bin/env python3
"""
Image Document Extractor

Extracts text from images using OCR (Tesseract).
Supports common image formats: PNG, JPG, JPEG, GIF, BMP, TIFF.
"""

import json
import os
from pathlib import Path
from typing import Dict, Any, List, Tuple
from PIL import Image, ImageEnhance, ImageFilter
import pytesseract
import cv2
import numpy as np


class ImageExtractor:
    def __init__(self):
        self.supported_formats = ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'tiff', 'tif', 'webp']

        # Try to configure Tesseract path (common locations)
        possible_paths = [
            '/usr/bin/tesseract',
            '/usr/local/bin/tesseract',
            '/opt/homebrew/bin/tesseract',  # macOS with Homebrew
            'tesseract'  # Assume it's in PATH
        ]

        for path in possible_paths:
            try:
                if os.path.exists(path) or path == 'tesseract':
                    pytesseract.pytesseract.tesseract_cmd = path
                    break
            except:
                continue

    def extract(self, file_path: str, language: str = 'por+eng', preprocess: bool = True) -> Dict[str, Any]:
        """Extract text from image using OCR"""
        try:
            file_path = Path(file_path)

            if not file_path.exists():
                return self._error_response(f"File not found: {file_path}")

            # Validate file format
            extension = file_path.suffix.lower().lstrip('.')
            if extension not in self.supported_formats:
                return self._error_response(f"Unsupported image format: {extension}")

            # Load image
            try:
                image = Image.open(file_path)
                original_size = image.size
            except Exception as e:
                return self._error_response(f"Cannot open image: {str(e)}")

            # Initialize stats
            stats = {
                'total_elements': 1,  # The image itself
                'extracted_elements': 0,
                'extraction_percentage': 0.0
            }

            issues = []
            recommendations = []

            # Image preprocessing if requested
            processed_image = image
            preprocessing_steps = []

            if preprocess:
                processed_image, preprocessing_steps = self._preprocess_image(image)

            # Extract text using Tesseract
            extracted_content = {
                'text_content': '',
                'confidence_scores': [],
                'word_details': [],
                'line_details': [],
                'block_details': [],
                'image_info': {
                    'width': original_size[0],
                    'height': original_size[1],
                    'format': image.format,
                    'mode': image.mode,
                    'preprocessing_applied': preprocessing_steps
                },
                'ocr_metadata': {}
            }

            try:
                # Basic text extraction
                text = pytesseract.image_to_string(processed_image, lang=language)
                extracted_content['text_content'] = text.strip()

                if extracted_content['text_content']:
                    stats['extracted_elements'] = 1

                # Detailed OCR data
                ocr_data = pytesseract.image_to_data(processed_image, lang=language, output_type=pytesseract.Output.DICT)

                # Process OCR results
                self._process_ocr_data(ocr_data, extracted_content)

                # Get confidence score
                try:
                    confidence_scores = [int(conf) for conf in ocr_data['conf'] if int(conf) > 0]
                    if confidence_scores:
                        avg_confidence = sum(confidence_scores) / len(confidence_scores)
                        extracted_content['ocr_metadata']['average_confidence'] = round(avg_confidence, 2)
                        extracted_content['ocr_metadata']['confidence_distribution'] = {
                            'high': len([c for c in confidence_scores if c >= 80]),
                            'medium': len([c for c in confidence_scores if 50 <= c < 80]),
                            'low': len([c for c in confidence_scores if c < 50])
                        }
                except:
                    extracted_content['ocr_metadata']['average_confidence'] = 0

                # Detect orientation
                try:
                    osd = pytesseract.image_to_osd(processed_image)
                    orientation = self._parse_osd(osd)
                    extracted_content['ocr_metadata']['orientation'] = orientation
                except:
                    extracted_content['ocr_metadata']['orientation'] = {'angle': 0, 'confidence': 0}

            except Exception as e:
                issues.append(f"OCR processing failed: {str(e)}")
                recommendations.append("Check if Tesseract is properly installed")

            # Calculate extraction percentage
            if extracted_content['text_content']:
                stats['extraction_percentage'] = 100.0
            else:
                stats['extraction_percentage'] = 0.0

            # Quality analysis
            text_length = len(extracted_content['text_content'])

            if text_length == 0:
                issues.append("No text detected in image")
                recommendations.append("Check image quality and try preprocessing")
                recommendations.append("Verify the image contains readable text")

            if extracted_content['ocr_metadata'].get('average_confidence', 0) < 50:
                issues.append("Low OCR confidence score")
                recommendations.append("Improve image resolution or quality")

            # Image quality checks
            image_area = original_size[0] * original_size[1]
            if image_area < 100000:  # Less than ~316x316
                issues.append("Low resolution image")
                recommendations.append("Use higher resolution image for better OCR results")

            # Check for potential issues
            if extracted_content['ocr_metadata'].get('orientation', {}).get('angle', 0) != 0:
                angle = extracted_content['ocr_metadata']['orientation']['angle']
                issues.append(f"Image appears rotated ({angle} degrees)")
                recommendations.append("Rotate image to correct orientation before OCR")

            # Text analysis
            if text_length > 0:
                word_count = len(extracted_content['text_content'].split())
                extracted_content['ocr_metadata']['word_count'] = word_count
                extracted_content['ocr_metadata']['character_count'] = text_length

                if word_count < 5:
                    issues.append("Very little text detected")
                    recommendations.append("Verify image contains substantial text content")

            # Determine quality status
            avg_conf = extracted_content['ocr_metadata'].get('average_confidence', 0)

            if text_length > 50 and avg_conf >= 75 and len(issues) <= 1:
                status = "GOOD"
            elif text_length > 10 and avg_conf >= 50 and len(issues) <= 3:
                status = "FAIR"
            else:
                status = "POOR"

            return {
                "success": True,
                "file_type": f"image_{extension}",
                "extraction_stats": stats,
                "content": extracted_content,
                "quality_report": {
                    "status": status,
                    "issues": issues,
                    "recommendations": recommendations
                }
            }

        except Exception as e:
            return self._error_response(f"Error processing image: {str(e)}")

    def _preprocess_image(self, image: Image.Image) -> Tuple[Image.Image, List[str]]:
        """Apply preprocessing to improve OCR accuracy"""
        steps = []
        processed = image.copy()

        try:
            # Convert to RGB if necessary
            if processed.mode != 'RGB':
                processed = processed.convert('RGB')
                steps.append("converted_to_rgb")

            # Convert to OpenCV format for advanced preprocessing
            cv_image = cv2.cvtColor(np.array(processed), cv2.COLOR_RGB2BGR)

            # Convert to grayscale
            gray = cv2.cvtColor(cv_image, cv2.COLOR_BGR2GRAY)
            steps.append("grayscale_conversion")

            # Apply denoising
            denoised = cv2.fastNlMeansDenoising(gray)
            steps.append("noise_reduction")

            # Apply adaptive thresholding
            thresh = cv2.adaptiveThreshold(
                denoised, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, 11, 2
            )
            steps.append("adaptive_thresholding")

            # Morphological operations to clean up
            kernel = np.ones((1, 1), np.uint8)
            cleaned = cv2.morphologyEx(thresh, cv2.MORPH_CLOSE, kernel)
            steps.append("morphological_cleanup")

            # Convert back to PIL Image
            processed = Image.fromarray(cleaned)

            # Enhance contrast
            enhancer = ImageEnhance.Contrast(processed)
            processed = enhancer.enhance(1.2)
            steps.append("contrast_enhancement")

            # Sharpen image
            processed = processed.filter(ImageFilter.SHARPEN)
            steps.append("sharpening")

        except Exception as e:
            # If preprocessing fails, return original image
            steps.append(f"preprocessing_failed: {str(e)}")
            processed = image

        return processed, steps

    def _process_ocr_data(self, ocr_data: dict, extracted_content: dict):
        """Process detailed OCR data"""
        try:
            # Process word-level details
            words = []
            for i in range(len(ocr_data['text'])):
                if int(ocr_data['conf'][i]) > 0 and ocr_data['text'][i].strip():
                    word_info = {
                        'text': ocr_data['text'][i],
                        'confidence': int(ocr_data['conf'][i]),
                        'bbox': {
                            'left': int(ocr_data['left'][i]),
                            'top': int(ocr_data['top'][i]),
                            'width': int(ocr_data['width'][i]),
                            'height': int(ocr_data['height'][i])
                        },
                        'block_num': int(ocr_data['block_num'][i]),
                        'par_num': int(ocr_data['par_num'][i]),
                        'line_num': int(ocr_data['line_num'][i]),
                        'word_num': int(ocr_data['word_num'][i])
                    }
                    words.append(word_info)

            extracted_content['word_details'] = words

            # Group by lines
            lines = {}
            for word in words:
                line_key = f"{word['block_num']}_{word['par_num']}_{word['line_num']}"
                if line_key not in lines:
                    lines[line_key] = {
                        'text': '',
                        'words': [],
                        'confidence_avg': 0,
                        'bbox': None
                    }
                lines[line_key]['words'].append(word)

            # Process lines
            line_details = []
            for line_key, line_data in lines.items():
                line_text = ' '.join([w['text'] for w in line_data['words']])
                avg_conf = sum([w['confidence'] for w in line_data['words']]) / len(line_data['words'])

                # Calculate line bounding box
                left = min([w['bbox']['left'] for w in line_data['words']])
                top = min([w['bbox']['top'] for w in line_data['words']])
                right = max([w['bbox']['left'] + w['bbox']['width'] for w in line_data['words']])
                bottom = max([w['bbox']['top'] + w['bbox']['height'] for w in line_data['words']])

                line_info = {
                    'text': line_text,
                    'confidence_avg': round(avg_conf, 2),
                    'word_count': len(line_data['words']),
                    'bbox': {
                        'left': left,
                        'top': top,
                        'width': right - left,
                        'height': bottom - top
                    }
                }
                line_details.append(line_info)

            extracted_content['line_details'] = line_details

            # Group by blocks
            blocks = {}
            for word in words:
                block_key = word['block_num']
                if block_key not in blocks:
                    blocks[block_key] = []
                blocks[block_key].append(word)

            # Process blocks
            block_details = []
            for block_num, block_words in blocks.items():
                block_text = ' '.join([w['text'] for w in block_words])
                avg_conf = sum([w['confidence'] for w in block_words]) / len(block_words)

                block_info = {
                    'block_num': block_num,
                    'text': block_text,
                    'confidence_avg': round(avg_conf, 2),
                    'word_count': len(block_words),
                    'line_count': len(set([f"{w['par_num']}_{w['line_num']}" for w in block_words]))
                }
                block_details.append(block_info)

            extracted_content['block_details'] = block_details

        except Exception as e:
            extracted_content['ocr_metadata']['processing_error'] = str(e)

    def _parse_osd(self, osd_text: str) -> Dict[str, Any]:
        """Parse Orientation and Script Detection output"""
        lines = osd_text.strip().split('\n')
        orientation = {'angle': 0, 'confidence': 0}

        for line in lines:
            if 'Orientation in degrees:' in line:
                try:
                    orientation['angle'] = int(line.split(':')[1].strip())
                except:
                    pass
            elif 'Orientation confidence:' in line:
                try:
                    orientation['confidence'] = float(line.split(':')[1].strip())
                except:
                    pass

        return orientation

    def _error_response(self, message: str) -> Dict[str, Any]:
        """Generate error response"""
        return {
            "success": False,
            "file_type": "image",
            "extraction_stats": {
                "total_elements": 0,
                "extracted_elements": 0,
                "extraction_percentage": 0.0
            },
            "content": {},
            "quality_report": {
                "status": "ERROR",
                "issues": [message],
                "recommendations": ["Fix the error and try again"]
            }
        }


if __name__ == "__main__":
    import sys

    if len(sys.argv) < 2:
        print("Usage: python image_extractor.py <file_path> [language] [preprocess]")
        print("Example: python image_extractor.py image.png por+eng true")
        sys.exit(1)

    file_path = sys.argv[1]
    language = sys.argv[2] if len(sys.argv) > 2 else 'por+eng'
    preprocess = sys.argv[3].lower() == 'true' if len(sys.argv) > 3 else True

    extractor = ImageExtractor()
    result = extractor.extract(file_path, language, preprocess)
    print(json.dumps(result, indent=2, ensure_ascii=False))