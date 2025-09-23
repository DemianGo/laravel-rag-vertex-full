"""
Notification service for webhooks and alerts.
"""

import asyncio
import httpx
from typing import Optional, Dict, Any, List
from datetime import datetime
import json

from core.config import settings
from core.logging import get_logger
from models.enums import NotificationType, ExtractionStatus

logger = get_logger("notification_service")


class NotificationService:
    """Service for sending webhooks and notifications."""

    def __init__(self):
        self.webhook_timeout = settings.webhook_timeout
        self.retry_attempts = settings.webhook_retry_attempts
        self._pending_notifications: List[Dict[str, Any]] = []

    async def send_webhook(
        self,
        webhook_url: str,
        payload: Dict[str, Any],
        notification_type: NotificationType,
        retry_count: int = 0
    ) -> bool:
        """Send webhook notification with retry logic."""

        try:
            headers = {
                "Content-Type": "application/json",
                "User-Agent": f"{settings.app_name}/{settings.app_version}",
                "X-Notification-Type": notification_type.value,
                "X-Timestamp": datetime.utcnow().isoformat(),
            }

            # Add signature if secret key is configured
            if settings.secret_key:
                signature = self._generate_signature(json.dumps(payload), settings.secret_key)
                headers["X-Signature"] = f"sha256={signature}"

            async with httpx.AsyncClient(timeout=self.webhook_timeout) as client:
                response = await client.post(
                    webhook_url,
                    json=payload,
                    headers=headers
                )
                response.raise_for_status()

                logger.info(
                    "Webhook sent successfully",
                    url=webhook_url,
                    type=notification_type.value,
                    status_code=response.status_code,
                    retry_count=retry_count
                )
                return True

        except Exception as e:
            logger.warning(
                "Webhook send failed",
                url=webhook_url,
                type=notification_type.value,
                error=str(e),
                retry_count=retry_count
            )

            # Retry logic
            if retry_count < self.retry_attempts:
                # Exponential backoff
                delay = 2 ** retry_count
                await asyncio.sleep(delay)
                return await self.send_webhook(webhook_url, payload, notification_type, retry_count + 1)

            logger.error(
                "Webhook failed after all retries",
                url=webhook_url,
                type=notification_type.value,
                error=str(e),
                total_attempts=retry_count + 1
            )
            return False

    async def notify_job_completed(
        self,
        webhook_url: str,
        job_id: str,
        result: Dict[str, Any],
        metadata: Optional[Dict[str, Any]] = None
    ):
        """Send job completion notification."""
        payload = {
            "event": "job_completed",
            "job_id": job_id,
            "timestamp": datetime.utcnow().isoformat(),
            "result": result,
            "metadata": metadata or {}
        }

        await self.send_webhook(webhook_url, payload, NotificationType.JOB_COMPLETED)

    async def notify_job_failed(
        self,
        webhook_url: str,
        job_id: str,
        error: Dict[str, Any],
        metadata: Optional[Dict[str, Any]] = None
    ):
        """Send job failure notification."""
        payload = {
            "event": "job_failed",
            "job_id": job_id,
            "timestamp": datetime.utcnow().isoformat(),
            "error": error,
            "metadata": metadata or {}
        }

        await self.send_webhook(webhook_url, payload, NotificationType.JOB_FAILED)

    async def notify_batch_completed(
        self,
        webhook_url: str,
        batch_id: str,
        results: Dict[str, Any],
        metadata: Optional[Dict[str, Any]] = None
    ):
        """Send batch completion notification."""
        payload = {
            "event": "batch_completed",
            "batch_id": batch_id,
            "timestamp": datetime.utcnow().isoformat(),
            "results": results,
            "metadata": metadata or {}
        }

        await self.send_webhook(webhook_url, payload, NotificationType.BATCH_COMPLETED)

    async def send_error_alert(
        self,
        webhook_url: str,
        error_details: Dict[str, Any],
        severity: str = "error"
    ):
        """Send error alert notification."""
        payload = {
            "event": "error_alert",
            "severity": severity,
            "timestamp": datetime.utcnow().isoformat(),
            "error": error_details,
            "system": {
                "app_name": settings.app_name,
                "version": settings.app_version,
                "environment": settings.environment
            }
        }

        await self.send_webhook(webhook_url, payload, NotificationType.ERROR_ALERT)

    def queue_notification(
        self,
        webhook_url: str,
        payload: Dict[str, Any],
        notification_type: NotificationType
    ):
        """Queue a notification for async processing."""
        notification = {
            "webhook_url": webhook_url,
            "payload": payload,
            "notification_type": notification_type,
            "queued_at": datetime.utcnow(),
            "attempts": 0
        }

        self._pending_notifications.append(notification)
        logger.debug("Notification queued", type=notification_type.value)

    async def process_pending_notifications(self):
        """Process all pending notifications."""
        if not self._pending_notifications:
            return

        notifications = self._pending_notifications.copy()
        self._pending_notifications.clear()

        tasks = []
        for notification in notifications:
            task = self.send_webhook(
                notification["webhook_url"],
                notification["payload"],
                notification["notification_type"]
            )
            tasks.append(task)

        if tasks:
            results = await asyncio.gather(*tasks, return_exceptions=True)

            # Log results
            successful = sum(1 for r in results if r is True)
            failed = len(results) - successful

            logger.info(
                "Processed pending notifications",
                total=len(results),
                successful=successful,
                failed=failed
            )

    def _generate_signature(self, payload: str, secret: str) -> str:
        """Generate HMAC signature for webhook payload."""
        import hmac
        import hashlib

        return hmac.new(
            secret.encode(),
            payload.encode(),
            hashlib.sha256
        ).hexdigest()

    def get_pending_count(self) -> int:
        """Get number of pending notifications."""
        return len(self._pending_notifications)

    async def health_check(self) -> Dict[str, Any]:
        """Perform health check on notification service."""
        return {
            "status": "healthy",
            "pending_notifications": len(self._pending_notifications),
            "webhook_timeout": self.webhook_timeout,
            "retry_attempts": self.retry_attempts,
            "enabled": settings.enable_webhooks
        }