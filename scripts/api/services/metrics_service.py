"""
Metrics collection and reporting service.
"""

import time
from typing import Dict, List, Optional, Any
from datetime import datetime, timedelta
from collections import defaultdict, deque
import asyncio
from dataclasses import dataclass, field

from core.logging import get_logger
from models.responses import MetricPoint

logger = get_logger("metrics_service")


@dataclass
class MetricData:
    """Container for metric data points."""
    name: str
    values: deque = field(default_factory=lambda: deque(maxlen=10000))
    labels: Dict[str, str] = field(default_factory=dict)
    last_updated: datetime = field(default_factory=datetime.utcnow)


class MetricsService:
    """Service for collecting and reporting application metrics."""

    def __init__(self):
        self.metrics: Dict[str, MetricData] = {}
        self.counters: Dict[str, float] = defaultdict(float)
        self.gauges: Dict[str, float] = {}
        self.histograms: Dict[str, List[float]] = defaultdict(list)
        self.start_time = time.time()

        # Built-in metrics
        self._init_builtin_metrics()

    def _init_builtin_metrics(self):
        """Initialize built-in system metrics."""
        self.counters.update({
            "http_requests_total": 0,
            "extraction_requests_total": 0,
            "extraction_errors_total": 0,
            "cache_hits_total": 0,
            "cache_misses_total": 0,
        })

        self.gauges.update({
            "active_jobs": 0,
            "memory_usage_bytes": 0,
            "cpu_usage_percent": 0,
        })

    def increment_counter(self, name: str, value: float = 1.0, labels: Optional[Dict[str, str]] = None):
        """Increment a counter metric."""
        full_name = self._build_metric_name(name, labels)
        self.counters[full_name] += value

        # Also record as time series
        self._record_metric_point(name, value, labels)

        logger.debug("Counter incremented", metric=name, value=value, labels=labels)

    def set_gauge(self, name: str, value: float, labels: Optional[Dict[str, str]] = None):
        """Set a gauge metric value."""
        full_name = self._build_metric_name(name, labels)
        self.gauges[full_name] = value

        # Also record as time series
        self._record_metric_point(name, value, labels)

        logger.debug("Gauge set", metric=name, value=value, labels=labels)

    def record_histogram(self, name: str, value: float, labels: Optional[Dict[str, str]] = None):
        """Record a value in a histogram."""
        full_name = self._build_metric_name(name, labels)
        self.histograms[full_name].append(value)

        # Limit histogram size
        if len(self.histograms[full_name]) > 10000:
            self.histograms[full_name] = self.histograms[full_name][-5000:]

        # Also record as time series
        self._record_metric_point(name, value, labels)

        logger.debug("Histogram recorded", metric=name, value=value, labels=labels)

    def time_function(self, metric_name: str, labels: Optional[Dict[str, str]] = None):
        """Decorator to time function execution."""
        def decorator(func):
            async def async_wrapper(*args, **kwargs):
                start_time = time.time()
                try:
                    result = await func(*args, **kwargs)
                    duration = time.time() - start_time
                    self.record_histogram(f"{metric_name}_duration_seconds", duration, labels)
                    self.increment_counter(f"{metric_name}_total", 1.0, labels)
                    return result
                except Exception as e:
                    duration = time.time() - start_time
                    self.record_histogram(f"{metric_name}_duration_seconds", duration, labels)
                    error_labels = {**(labels or {}), "error": type(e).__name__}
                    self.increment_counter(f"{metric_name}_errors_total", 1.0, error_labels)
                    raise

            def sync_wrapper(*args, **kwargs):
                start_time = time.time()
                try:
                    result = func(*args, **kwargs)
                    duration = time.time() - start_time
                    self.record_histogram(f"{metric_name}_duration_seconds", duration, labels)
                    self.increment_counter(f"{metric_name}_total", 1.0, labels)
                    return result
                except Exception as e:
                    duration = time.time() - start_time
                    self.record_histogram(f"{metric_name}_duration_seconds", duration, labels)
                    error_labels = {**(labels or {}), "error": type(e).__name__}
                    self.increment_counter(f"{metric_name}_errors_total", 1.0, error_labels)
                    raise

            return async_wrapper if asyncio.iscoroutinefunction(func) else sync_wrapper
        return decorator

    def get_metrics(self, time_window: int = 3600) -> Dict[str, List[MetricPoint]]:
        """Get metrics within specified time window."""
        cutoff_time = datetime.utcnow() - timedelta(seconds=time_window)
        result = {}

        for metric_name, metric_data in self.metrics.items():
            points = []
            for timestamp, value in metric_data.values:
                if timestamp >= cutoff_time:
                    points.append(MetricPoint(
                        timestamp=timestamp,
                        value=value,
                        labels=metric_data.labels
                    ))

            if points:
                result[metric_name] = points

        return result

    def get_summary_stats(self) -> Dict[str, Any]:
        """Get summary statistics for all metrics."""
        now = time.time()
        uptime = now - self.start_time

        stats = {
            "uptime_seconds": uptime,
            "counters": dict(self.counters),
            "gauges": dict(self.gauges),
            "histogram_stats": {}
        }

        # Calculate histogram statistics
        for name, values in self.histograms.items():
            if values:
                sorted_values = sorted(values)
                length = len(sorted_values)

                stats["histogram_stats"][name] = {
                    "count": length,
                    "sum": sum(sorted_values),
                    "min": min(sorted_values),
                    "max": max(sorted_values),
                    "mean": sum(sorted_values) / length,
                    "p50": sorted_values[int(length * 0.5)] if length > 0 else 0,
                    "p90": sorted_values[int(length * 0.9)] if length > 0 else 0,
                    "p95": sorted_values[int(length * 0.95)] if length > 0 else 0,
                    "p99": sorted_values[int(length * 0.99)] if length > 0 else 0,
                }

        return stats

    def get_prometheus_metrics(self) -> str:
        """Export metrics in Prometheus format."""
        lines = []

        # Counters
        for name, value in self.counters.items():
            lines.append(f"# TYPE {name} counter")
            lines.append(f"{name} {value}")

        # Gauges
        for name, value in self.gauges.items():
            lines.append(f"# TYPE {name} gauge")
            lines.append(f"{name} {value}")

        # Histograms
        for name, values in self.histograms.items():
            if values:
                sorted_values = sorted(values)
                lines.append(f"# TYPE {name} histogram")

                # Histogram buckets
                buckets = [0.005, 0.01, 0.025, 0.05, 0.075, 0.1, 0.25, 0.5, 0.75, 1.0, 2.5, 5.0, 7.5, 10.0]
                cumulative_count = 0

                for bucket in buckets:
                    count = sum(1 for v in sorted_values if v <= bucket)
                    lines.append(f'{name}_bucket{{le="{bucket}"}} {count}')

                lines.append(f'{name}_bucket{{le="+Inf"}} {len(sorted_values)}')
                lines.append(f"{name}_count {len(sorted_values)}")
                lines.append(f"{name}_sum {sum(sorted_values)}")

        return "\n".join(lines) + "\n"

    def record_request_metrics(self, method: str, path: str, status_code: int, duration: float):
        """Record HTTP request metrics."""
        labels = {
            "method": method,
            "path": path,
            "status": str(status_code)
        }

        self.increment_counter("http_requests_total", 1.0, labels)
        self.record_histogram("http_request_duration_seconds", duration, labels)

        if status_code >= 400:
            self.increment_counter("http_errors_total", 1.0, labels)

    def record_extraction_metrics(self, file_type: str, success: bool, duration: float, file_size: int):
        """Record extraction-specific metrics."""
        labels = {
            "file_type": file_type,
            "success": str(success).lower()
        }

        self.increment_counter("extraction_requests_total", 1.0, labels)
        self.record_histogram("extraction_duration_seconds", duration, labels)
        self.record_histogram("extraction_file_size_bytes", float(file_size), labels)

        if not success:
            self.increment_counter("extraction_errors_total", 1.0, labels)

    def _record_metric_point(self, name: str, value: float, labels: Optional[Dict[str, str]] = None):
        """Record a metric data point with timestamp."""
        if name not in self.metrics:
            self.metrics[name] = MetricData(name=name, labels=labels or {})

        self.metrics[name].values.append((datetime.utcnow(), value))
        self.metrics[name].last_updated = datetime.utcnow()

    def _build_metric_name(self, name: str, labels: Optional[Dict[str, str]] = None) -> str:
        """Build full metric name with labels."""
        if not labels:
            return name

        label_string = ",".join(f"{k}={v}" for k, v in sorted(labels.items()))
        return f"{name}{{{label_string}}}"

    async def update_system_metrics(self):
        """Update system-level metrics."""
        try:
            import psutil

            # Memory usage
            memory = psutil.virtual_memory()
            self.set_gauge("memory_usage_bytes", memory.used)
            self.set_gauge("memory_usage_percent", memory.percent)

            # CPU usage
            cpu_percent = psutil.cpu_percent(interval=1)
            self.set_gauge("cpu_usage_percent", cpu_percent)

            # Disk usage
            disk = psutil.disk_usage('/')
            self.set_gauge("disk_usage_bytes", disk.used)
            self.set_gauge("disk_usage_percent", (disk.used / disk.total) * 100)

        except ImportError:
            logger.debug("psutil not available, skipping system metrics")
        except Exception as e:
            logger.error("Error updating system metrics", error=str(e))