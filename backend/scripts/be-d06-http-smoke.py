#!/usr/bin/env python3
"""Exercise the D04 synthetic fixtures through the D06 local HTTP runtime."""

from __future__ import annotations

import argparse
import http.cookiejar
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path
from typing import Any


BASE_URL = os.environ.get("BE_D06_SMOKE_URL", "http://127.0.0.1:18006").rstrip("/")
FRONTEND_ORIGIN = "http://localhost:3000"
FIXTURE_PASSWORD = os.environ.get("BE_D06_FIXTURE_PASSWORD", "password")
PROGRAM_ID = 1
TODO_TASK_ID = 1

SAFE_HEADERS = ("Content-Type", "Cache-Control", "X-Request-ID", "Retry-After")
SAFE_FIELDS = {
    "id",
    "status",
    "version",
    "submission_version",
    "title",
    "body",
    "amount_minor",
    "currency",
    "fee_date",
    "progress_date",
    "created_at",
    "updated_at",
    "due_at",
    "goal",
    "completion_rate",
    "planned_session_count",
    "task_counts",
    "session_counts",
    "current_page",
    "per_page",
    "total",
    "last_page",
    "code",
    "message",
    "request_id",
    "errors",
    "feedback",
    "files",
    "purpose",
    "mime_type",
    "size_bytes",
    "original_name",
}


class HttpClient:
    def __init__(self, actor: str):
        self.actor = actor
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))

    def xsrf_token(self) -> str | None:
        return next(
            (urllib.parse.unquote(cookie.value) for cookie in self.jar if cookie.name == "XSRF-TOKEN"),
            None,
        )

    def request(
        self,
        method: str,
        path: str,
        body: dict[str, Any] | None = None,
        headers: dict[str, str] | None = None,
        *,
        csrf: bool = True,
    ) -> dict[str, Any]:
        request_headers = {
            "Accept": "application/json",
            "Origin": FRONTEND_ORIGIN,
            "Referer": FRONTEND_ORIGIN + "/login",
            "X-Requested-With": "XMLHttpRequest",
        }
        if headers:
            request_headers.update(headers)
        if csrf and method in {"POST", "PUT", "PATCH", "DELETE"}:
            token = self.xsrf_token()
            if token:
                request_headers["X-XSRF-TOKEN"] = token
        payload = None if body is None else json.dumps(body, ensure_ascii=False).encode()
        if payload is not None:
            request_headers["Content-Type"] = "application/json"
        request = urllib.request.Request(BASE_URL + path, data=payload, headers=request_headers, method=method)
        try:
            with self.opener.open(request, timeout=12) as response:
                status, response_headers, response_body = response.status, response.headers, response.read()
        except urllib.error.HTTPError as error:
            status, response_headers, response_body = error.code, error.headers, error.read()

        try:
            decoded = json.loads(response_body) if response_body else None
        except json.JSONDecodeError:
            decoded = None

        request_body = dict(body or {})
        if "password" in request_body:
            request_body["password"] = "[redacted]"
        if "email" in request_body:
            request_body["email"] = "[synthetic fixture actor]"
        result = {
            "actor": self.actor,
            "method": method,
            "path": path,
            "request_body": request_body or None,
            "request_headers": {
                name: value
                for name, value in (headers or {}).items()
                if name.lower() == "idempotency-key"
            },
            "status": status,
            "headers": {name: response_headers.get(name) for name in SAFE_HEADERS if response_headers.get(name)},
            "response": summarize(decoded),
        }
        return result

    def csrf_cookie(self) -> dict[str, Any]:
        return self.request("GET", "/sanctum/csrf-cookie")

def summarize(value: Any) -> Any:
    if isinstance(value, list):
        return [summarize(item) for item in value[:2]]
    if not isinstance(value, dict):
        return value
    result = {key: summarize(item) for key, item in value.items() if key in SAFE_FIELDS}
    if isinstance(value.get("errors"), dict):
        result["errors"] = {
            key: [str(message) for message in messages]
            for key, messages in value["errors"].items()
        }
    if "data" in value:
        data = value["data"]
        if isinstance(data, list):
            result["data_count"] = len(data)
        result["data"] = summarize(data)
    if "meta" in value:
        result["meta"] = summarize(value["meta"])
    return result


def expected(trace: list[dict[str, Any]], result: dict[str, Any], status: int, label: str) -> None:
    result["check"] = {"label": label, "expected_status": status, "passed": result["status"] == status}
    trace.append(result)


def run() -> dict[str, Any]:
    trace: list[dict[str, Any]] = []
    visitor = HttpClient("anonymous")
    expected(trace, visitor.request("GET", "/up"), 200, "health")

    csrf_visitor = HttpClient("anonymous_csrf")
    csrf_visitor.csrf_cookie()
    expected(
        trace,
        csrf_visitor.request(
            "POST",
            "/api/v1/auth/login",
            {"email": "be-d06-csrf@tutorlink.test", "password": FIXTURE_PASSWORD},
            csrf=False,
        ),
        419,
        "missing_csrf_header",
    )
    expected(
        trace,
        visitor.request("GET", f"/api/v1/programs/{PROGRAM_ID}/overview"),
        401,
        "unauthenticated_overview",
    )

    owner = HttpClient("owner")
    expected(trace, owner.csrf_cookie(), 204, "owner_csrf_cookie")
    expected(
        trace,
        owner.request(
            "POST",
            "/api/v1/auth/login",
            {"email": "be-d04-owner@tutorlink.test", "password": FIXTURE_PASSWORD},
        ),
        200,
        "owner_login",
    )
    member = HttpClient("late_member")
    expected(trace, member.csrf_cookie(), 204, "member_csrf_cookie")
    expected(
        trace,
        member.request(
            "POST",
            "/api/v1/auth/login",
            {"email": "be-d04-late-member@tutorlink.test", "password": FIXTURE_PASSWORD},
        ),
        200,
        "member_login",
    )

    expected(
        trace,
        owner.request("GET", f"/api/v1/programs/{PROGRAM_ID}/overview"),
        200,
        "program_overview",
    )
    expected(
        trace,
        member.request("GET", f"/api/v1/programs/{PROGRAM_ID}/progress-records?per_page=2"),
        200,
        "progress_list",
    )
    expected(
        trace,
        member.request("GET", f"/api/v1/programs/{PROGRAM_ID}/announcements?per_page=2"),
        200,
        "announcement_list",
    )
    expected(
        trace,
        member.request("GET", "/api/v1/announcements/1"),
        200,
        "announcement_detail",
    )
    expected(
        trace,
        member.request("GET", f"/api/v1/programs/{PROGRAM_ID}/fee-records?per_page=2"),
        200,
        "fee_list",
    )
    expected(
        trace,
        member.request("GET", f"/api/v1/programs/{PROGRAM_ID}/tasks?per_page=5"),
        200,
        "task_list",
    )
    expected(
        trace,
        member.request("GET", "/api/v1/tasks/999999/submission"),
        404,
        "unknown_task",
    )
    expected(
        trace,
        owner.request("POST", f"/api/v1/tasks/{TODO_TASK_ID}/start", {"expected_version": 1}),
        403,
        "wrong_actor_cannot_start_task",
    )

    expected(
        trace,
        member.request("POST", f"/api/v1/tasks/{TODO_TASK_ID}/start", {"expected_version": 1}),
        200,
        "start_task",
    )
    expected(
        trace,
        member.request("POST", f"/api/v1/tasks/{TODO_TASK_ID}/start", {"expected_version": 1}),
        409,
        "stale_task_version",
    )
    submission_key = str(uuid.UUID("00000000-0000-4000-8000-000000000601"))
    submission_path = f"/api/v1/tasks/{TODO_TASK_ID}/submission"
    submission_body = {"expected_submission_version": 0, "content": "D06 HTTP smoke submission"}
    created_submission = member.request(
        "PUT", submission_path, submission_body, {"Idempotency-Key": submission_key}
    )
    expected(
        trace,
        created_submission,
        201,
        "create_submission",
    )
    submission_id = (created_submission.get("response") or {}).get("data", {}).get("id")
    replay_submission = member.request(
        "PUT", submission_path, submission_body, {"Idempotency-Key": submission_key}
    )
    expected(
        trace,
        replay_submission,
        200,
        "replay_submission",
    )
    replay_id = (replay_submission.get("response") or {}).get("data", {}).get("id")
    trace[-1]["check"]["same_resource_id"] = submission_id is not None and submission_id == replay_id
    trace[-1]["check"]["passed"] &= trace[-1]["check"]["same_resource_id"]
    expected(
        trace,
        member.request(
            "PUT",
            submission_path,
            {"expected_submission_version": 0, "content": "Stale D06 submission version"},
            {"Idempotency-Key": str(uuid.UUID("00000000-0000-4000-8000-000000000604"))},
        ),
        409,
        "stale_submission_version",
    )
    expected(
        trace,
        member.request(
            "PUT",
            submission_path,
            {"expected_submission_version": 0, "content": "Different D06 payload"},
            {"Idempotency-Key": submission_key},
        ),
        409,
        "submission_key_payload_mismatch",
    )
    expected(
        trace,
        member.request(
            "PUT",
            submission_path,
            {"expected_submission_version": -1, "content": "Invalid version"},
            {"Idempotency-Key": str(uuid.UUID("00000000-0000-4000-8000-000000000602"))},
        ),
        422,
        "invalid_submission_version",
    )

    feedback_key = str(uuid.UUID("00000000-0000-4000-8000-000000000603"))
    feedback_path = f"/api/v1/tasks/{TODO_TASK_ID}/submission/review"
    feedback_body = {"expected_submission_version": 1, "feedback": "D06 HTTP smoke accepted"}
    accepted_feedback = owner.request("POST", feedback_path, feedback_body, {"Idempotency-Key": feedback_key})
    expected(
        trace,
        accepted_feedback,
        200,
        "accept_submission",
    )
    accepted_id = (accepted_feedback.get("response") or {}).get("data", {}).get("id")
    replay_feedback = owner.request("POST", feedback_path, feedback_body, {"Idempotency-Key": feedback_key})
    expected(
        trace,
        replay_feedback,
        200,
        "replay_feedback",
    )
    feedback_replay_id = (replay_feedback.get("response") or {}).get("data", {}).get("id")
    trace[-1]["check"]["same_resource_id"] = accepted_id is not None and accepted_id == feedback_replay_id
    trace[-1]["check"]["passed"] &= trace[-1]["check"]["same_resource_id"]
    expected(
        trace,
        owner.request(
            "POST",
            feedback_path,
            {"expected_submission_version": 1, "feedback": "Changed D06 feedback"},
            {"Idempotency-Key": feedback_key},
        ),
        409,
        "feedback_key_payload_mismatch",
    )

    rate_limited = HttpClient("synthetic_rate_limit_probe")
    rate_limited.csrf_cookie()
    rate_results = []
    for _ in range(6):
        rate_results.append(
            rate_limited.request(
                "POST",
                "/api/v1/auth/login",
                {"email": "be-d06-rate-limit@tutorlink.test", "password": "wrong-password"},
            )
        )
    rate_result = rate_results[-1]
    rate_result["attempts_before_limit"] = [result["status"] for result in rate_results[:-1]]
    expected(trace, rate_result, 429, "login_rate_limit")
    trace[-1]["check"]["prior_attempts_were_401"] = all(
        status == 401 for status in rate_result["attempts_before_limit"]
    )
    trace[-1]["check"]["passed"] &= trace[-1]["check"]["prior_attempts_were_401"]

    return {
        "base_url": BASE_URL,
        "fixture_scope": "Synthetic BE-D04 fixtures in isolated tutorlink_test",
        "trace": trace,
        "passed": all(item["check"]["passed"] for item in trace),
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=Path)
    args = parser.parse_args()
    report = run()
    rendered = json.dumps(report, ensure_ascii=False, indent=2) + "\n"
    if args.output:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(rendered)
    print(rendered, end="")
    return 0 if report["passed"] else 1


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as error:
        print(f"D06 smoke failed: {type(error).__name__}: {error}", file=sys.stderr)
        sys.exit(1)
