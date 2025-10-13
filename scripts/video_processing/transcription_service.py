#!/usr/bin/env python3
"""
Universal Transcription Service
Supports: Google Speech-to-Text, Gemini Audio, OpenAI Whisper
"""

import sys
import os
import json
from pathlib import Path
from typing import Dict, List, Optional
import time

# Google Speech-to-Text
try:
    from google.cloud import speech
    GOOGLE_SPEECH_AVAILABLE = True
except ImportError:
    GOOGLE_SPEECH_AVAILABLE = False

# Google Gemini
try:
    import google.generativeai as genai
    GEMINI_AVAILABLE = True
except ImportError:
    GEMINI_AVAILABLE = False

# OpenAI Whisper
try:
    from openai import OpenAI
    OPENAI_AVAILABLE = True
except ImportError:
    OPENAI_AVAILABLE = False


class TranscriptionService:
    """Universal audio transcription service."""
    
    def __init__(self, api_key: Optional[str] = None, service: str = "auto"):
        """
        Initialize transcription service.
        
        Args:
            api_key: API key (Google, OpenAI, etc)
            service: "google", "gemini", "openai", or "auto" (tries in order)
        """
        self.api_key = api_key or os.getenv('GOOGLE_GENAI_API_KEY') or os.getenv('OPENAI_API_KEY')
        self.service = service
        
        # Configure Gemini if available
        if GEMINI_AVAILABLE and self.api_key:
            genai.configure(api_key=self.api_key)
    
    def transcribe(self, audio_path: str, language: str = "pt-BR") -> Dict:
        """
        Transcribe audio file.
        
        Args:
            audio_path: Path to audio file (mp3, wav, etc)
            language: Language code (pt-BR, en-US, es-ES, etc)
            
        Returns:
            {
                "success": bool,
                "text": str,
                "confidence": float,
                "duration": float,
                "language": str,
                "service_used": str,
                "timestamps": [{
                    "start": float,
                    "end": float,
                    "text": str
                }],
                "error": str (if failed)
            }
        """
        # Check if file exists
        audio_path = Path(audio_path)
        if not audio_path.exists():
            return {
                "success": False,
                "error": f"Audio file not found: {audio_path}"
            }
        
        # Check audio duration to choose best service
        # Gemini has output limit (~8k tokens), Google Speech is better for long audios
        audio_size_mb = audio_path.stat().st_size / (1024 * 1024)
        is_long_audio = audio_size_mb > 20  # > 20MB ~= > 20 minutes
        
        # Try services in order (prioritize Google Speech for long audios)
        if self.service == "auto":
            if is_long_audio and GOOGLE_SPEECH_AVAILABLE:
                services_to_try = ["google", "gemini", "openai"]
            else:
                services_to_try = ["gemini", "google", "openai"]
        else:
            services_to_try = [self.service]
        
        errors = []
        
        for service in services_to_try:
            if service == "gemini" and GEMINI_AVAILABLE:
                result = self._transcribe_gemini(audio_path, language)
                if result["success"]:
                    return result
                else:
                    errors.append(f"Gemini: {result.get('error', 'Unknown error')}")
            
            elif service == "google" and GOOGLE_SPEECH_AVAILABLE:
                result = self._transcribe_google(audio_path, language)
                if result["success"]:
                    return result
                else:
                    errors.append(f"Google Speech: {result.get('error', 'Unknown error')}")
            
            elif service == "openai" and OPENAI_AVAILABLE:
                result = self._transcribe_openai(audio_path, language)
                if result["success"]:
                    return result
                else:
                    errors.append(f"OpenAI: {result.get('error', 'Unknown error')}")
        
        return {
            "success": False,
            "error": "All transcription services failed: " + "; ".join(errors) if errors else "No transcription service available"
        }
    
    def _transcribe_gemini(self, audio_path: Path, language: str) -> Dict:
        """Transcribe using Google Gemini."""
        try:
            # Upload audio file
            audio_file = genai.upload_file(path=str(audio_path))
            
            # Wait for processing
            while audio_file.state.name == "PROCESSING":
                time.sleep(1)
                audio_file = genai.get_file(audio_file.name)
            
            if audio_file.state.name == "FAILED":
                return {
                    "success": False,
                    "error": "Gemini audio processing failed"
                }
            
            # Use gemini-1.5-pro for long audios (128k context, higher output limit)
            # Use gemini-2.5-flash for short audios (faster, cheaper, but 8k output limit)
            audio_size_mb = audio_path.stat().st_size / (1024 * 1024)
            
            if audio_size_mb > 15:  # > 15MB ~= > 15 minutes
                # Use gemini-2.5-pro for long audios (higher output limit)
                model_name = 'gemini-2.5-pro'
                max_tokens = 65536  # Maximum output tokens
                print(f"⚠️ Long audio detected ({audio_size_mb:.1f}MB). Using gemini-2.5-pro for complete transcription.", file=sys.stderr)
            else:
                # Use gemini-2.5-flash for short audios (faster, cheaper)
                model_name = 'gemini-2.5-flash'
                max_tokens = 8192
            
            # Create prompt for transcription
            model = genai.GenerativeModel(
                model_name,
                generation_config={
                    'max_output_tokens': max_tokens,
                    'temperature': 0.1,  # Low temperature for accurate transcription
                }
            )
            
            language_instructions = {
                "pt-BR": "Transcreva TODO o áudio em português brasileiro, do início ao fim, sem omitir nenhuma parte. Inclua TUDO que foi dito.",
                "en-US": "Transcribe ALL the audio in English, from start to finish, without omitting any part. Include EVERYTHING that was said.",
                "es-ES": "Transcribe TODO el audio en español, de principio a fin, sin omitir ninguna parte. Incluye TODO lo que se dijo."
            }
            
            prompt = language_instructions.get(language, language_instructions["en-US"])
            prompt += "\n\nIMPORTANTE: Forneça APENAS a transcrição completa, sem comentários, sem resumos, sem omissões. Transcreva palavra por palavra."
            
            # Generate transcription
            response = model.generate_content([prompt, audio_file])
            
            # Clean up uploaded file
            genai.delete_file(audio_file.name)
            
            return {
                "success": True,
                "text": response.text,
                "confidence": 0.9,  # Gemini doesn't provide confidence
                "duration": 0.0,  # Not available
                "language": language,
                "service_used": f"gemini ({model_name})",
                "timestamps": []  # Gemini doesn't provide timestamps
            }
        
        except Exception as e:
            return {
                "success": False,
                "error": f"Gemini transcription failed: {str(e)}"
            }
    
    def _transcribe_google(self, audio_path: Path, language: str) -> Dict:
        """Transcribe using Google Speech-to-Text."""
        try:
            client = speech.SpeechClient()
            
            # Read audio file
            with open(audio_path, 'rb') as audio_file:
                content = audio_file.read()
            
            audio = speech.RecognitionAudio(content=content)
            config = speech.RecognitionConfig(
                encoding=speech.RecognitionConfig.AudioEncoding.MP3,
                sample_rate_hertz=44100,
                language_code=language,
                enable_automatic_punctuation=True,
                enable_word_time_offsets=True
            )
            
            # Perform transcription
            response = client.recognize(config=config, audio=audio)
            
            if not response.results:
                return {
                    "success": False,
                    "error": "No transcription results"
                }
            
            # Extract text and timestamps
            full_text = []
            timestamps = []
            confidence_sum = 0.0
            confidence_count = 0
            
            for result in response.results:
                alternative = result.alternatives[0]
                full_text.append(alternative.transcript)
                confidence_sum += alternative.confidence
                confidence_count += 1
                
                # Extract word timestamps
                for word_info in alternative.words:
                    timestamps.append({
                        "start": word_info.start_time.total_seconds(),
                        "end": word_info.end_time.total_seconds(),
                        "text": word_info.word
                    })
            
            avg_confidence = confidence_sum / confidence_count if confidence_count > 0 else 0.0
            
            return {
                "success": True,
                "text": ' '.join(full_text),
                "confidence": avg_confidence,
                "duration": timestamps[-1]["end"] if timestamps else 0.0,
                "language": language,
                "service_used": "google_speech",
                "timestamps": timestamps
            }
        
        except Exception as e:
            return {
                "success": False,
                "error": f"Google Speech transcription failed: {str(e)}"
            }
    
    def _transcribe_openai(self, audio_path: Path, language: str) -> Dict:
        """Transcribe using OpenAI Whisper."""
        try:
            client = OpenAI(api_key=self.api_key)
            
            with open(audio_path, 'rb') as audio_file:
                # Use Whisper model
                response = client.audio.transcriptions.create(
                    model="whisper-1",
                    file=audio_file,
                    language=language.split('-')[0]  # Convert pt-BR to pt
                )
            
            return {
                "success": True,
                "text": response.text,
                "confidence": 0.9,  # Whisper doesn't provide confidence
                "duration": 0.0,  # Not available
                "language": language,
                "service_used": "openai_whisper",
                "timestamps": []  # Basic Whisper doesn't provide timestamps
            }
        
        except Exception as e:
            return {
                "success": False,
                "error": f"OpenAI Whisper transcription failed: {str(e)}"
            }


def main():
    """CLI interface."""
    if len(sys.argv) < 2:
        print(json.dumps({
            "success": False,
            "error": "Usage: transcription_service.py <audio_path> [language] [service]"
        }))
        sys.exit(1)
    
    audio_path = sys.argv[1]
    language = sys.argv[2] if len(sys.argv) > 2 else "pt-BR"
    service = sys.argv[3] if len(sys.argv) > 3 else "auto"
    
    transcriber = TranscriptionService(service=service)
    result = transcriber.transcribe(audio_path, language)
    
    print(json.dumps(result, indent=2, ensure_ascii=False))
    sys.exit(0 if result['success'] else 1)


if __name__ == "__main__":
    main()


