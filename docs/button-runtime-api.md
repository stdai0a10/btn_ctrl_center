# Button runtime API

## User button APIs

All endpoints require the normal authenticated user session.

|Method|Path|Purpose|
|---|---|---|
|GET|`/api/button-pages`|List the current user's button pages and button availability.|
|POST|`/api/button-pages`|Create a button page.|
|PATCH|`/api/button-pages/{button_page_public_id}`|Update button page name and columns.|
|PUT|`/api/button-pages/{button_page_public_id}/layout`|Replace one page's button layout.|
|DELETE|`/api/button-pages/{button_page_public_id}`|Delete a button page.|
|PUT|`/api/button-pages/order`|Reorder the current user's pages.|
|GET|`/api/buttons/selectable-targets`|List devices and product functions the user can bind to buttons.|
|POST|`/api/button-actions`|Create an action job for one button.|
|GET|`/api/button-actions/current`|Return the user's queued or running job, if any.|
|GET|`/api/button-actions/{button_action_job_public_id}`|Return one job owned by the current user.|

`POST /api/button-actions` requires:

- `button_public_id`
- `request_id`

`request_id` is idempotent per user. One user can have only one queued or running button job at a time.

## Device runtime APIs

Device endpoints do not use the normal user session. Devices use JWT Bearer tokens.

|Method|Path|Token|Purpose|
|---|---|---|---|
|POST|`/api/device-auth/long-token`|serial number + secret|Issue a long device JWT.|
|POST|`/api/devices/{serial_number}/access-tokens`|long JWT|Issue a short access JWT.|
|POST|`/api/devices/{serial_number}/poll`|access JWT|Poll the next job assigned to this device.|
|POST|`/api/device-jobs/{button_action_job_public_id}/progress`|access JWT|Report progress for a locked job.|
|POST|`/api/device-jobs/{button_action_job_public_id}/complete`|access JWT|Complete a locked job.|

Long JWTs can only request access JWTs. Access JWTs can only poll, report progress, and complete jobs.

The device job payload contains the product function code. It must not contain device secrets, user secrets, or full JWTs.

## Management APIs

All management APIs require manage login, `manage.access`, and the listed permissions.

|Method|Path|Permission|Purpose|
|---|---|---|---|
|GET|`/manage/api/device-runtime`|`manage.device_runtime.view`|List runtime status and token status for devices.|
|POST|`/manage/api/device-runtime/devices/{serial_number}/disable`|`manage.device_runtime.manage`|Disable device runtime and revoke current tokens.|
|POST|`/manage/api/device-runtime/devices/{serial_number}/enable`|`manage.device_runtime.manage`|Enable device runtime.|
|POST|`/manage/api/device-runtime/devices/{serial_number}/revoke-tokens`|`manage.device_runtime.manage`|Revoke current device JWT records and clear current access token state.|
|GET|`/manage/api/button-jobs`|`manage.button_jobs.view`|List button action jobs.|
|POST|`/manage/api/button-jobs/{button_action_job_public_id}/cancel`|`manage.button_jobs.cancel`|Cancel a queued or running job.|

Service managers can view runtime and job data. System admins can manage runtime, revoke tokens, and cancel jobs.

## Cleanup command

`button:cleanup-runtime` is scheduled every minute.

It handles:

- expired device access JWT records
- devices with stale heartbeats
- queued jobs whose device is offline
- running jobs whose lease expired
- frontend-timeout jobs after a 10 minute backend grace period

## Sensitive data rules

Responses and audit logs must not expose:

- full JWT strings
- device secrets
- `secret_hash`
- sensitive job payload data

Management responses may expose token status, expiry timestamps, token version, and active token counts, but not token plaintext or token `jti` values.
