import type { Instrumentation } from 'next';

/** Keep credentials, query strings, message content and stack traces out of structured event fields. */
export const onRequestError: Instrumentation.onRequestError = (error, request, context) => {
  console.error(JSON.stringify({
    event: 'tutorlink.server_error',
    occurred_at: new Date().toISOString(),
    method: request.method,
    route: context.routePath,
    route_type: context.routeType,
    digest: error && typeof error === 'object' && 'digest' in error ? String(error.digest).slice(0, 128) : undefined,
  }));
};
