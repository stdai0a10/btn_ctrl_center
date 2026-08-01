# Room Device Management API

All endpoints below require the existing authenticated API session middleware.

Error responses use the shared shape:

```json
{
  "message": "Cannot remove the last owner.",
  "data": null,
  "code": "ROOM_LAST_OWNER_REQUIRED"
}
```

## Rooms

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/api/rooms` | List rooms for the current user. |
| POST | `/api/rooms` | Create a room and attach the creator as `owner`. Body: `name`. |
| GET | `/api/rooms/{room}` | Show room, role, and members. Uses room `public_id`. |
| PATCH | `/api/rooms/{room}` | Owner only. Body: `name`. |
| DELETE | `/api/rooms/{room}` | Owner only. Clears devices and cancels pending invitations/requests. |

## Members

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/api/rooms/{room}/members` | Room members only. |
| DELETE | `/api/rooms/{room}/members/{user}` | Owner only. `{user}` is user `public_id`; owners cannot remove themselves. |
| POST | `/api/rooms/{room}/leave` | Current member leaves. Last owner alone deletes the room. |
| PATCH | `/api/rooms/{room}/members/{user}/role` | Owner only. Body: `role` as `owner` or `resident`. |

## Invitations

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/api/room-invitations` | Returns `received` and owner-visible `sent` invitations. |
| POST | `/api/rooms/{room}/invitations` | Owner only. Body: `invitee_public_id`. |
| POST | `/api/room-invitations/{invitation}/accept` | Invitee only. Adds invitee as `resident`. |
| POST | `/api/room-invitations/{invitation}/ignore` | Invitee only. |
| POST | `/api/room-invitations/{invitation}/cancel` | Room owner only. |

## Join Requests

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/api/room-join-requests` | Returns current user's `sent` requests and owner-visible `received` requests. |
| POST | `/api/rooms/{room}/join-requests` | Non-member asks to join. |
| POST | `/api/room-join-requests/{joinRequest}/accept` | Room owner only. Adds requester as `resident`. |
| POST | `/api/room-join-requests/{joinRequest}/ignore` | Room owner only. |
| POST | `/api/room-join-requests/{joinRequest}/cancel` | Requester only. Cancels pending or owner-ignored requests. |
| POST | `/api/room-join-requests/{joinRequest}/restore` | Room owner only. Restores an ignored request to pending unless it was cancelled. |

## Devices

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/api/rooms/{room}/devices` | Room members only. Secret is never returned. |
| POST | `/api/rooms/{room}/devices` | Owner only. Body: `serial_number`, `secret`, optional `name`, optional `lock`. Can transfer unlocked devices. |
| PATCH | `/api/rooms/{room}/devices/{device}` | Owner only. Body: `name`. |
| DELETE | `/api/rooms/{room}/devices/{device}` | Owner only. Device must be unlocked. |
| POST | `/api/rooms/{room}/devices/{device}/lock` | Owner only. |
| POST | `/api/rooms/{room}/devices/{device}/unlock` | Owner only. |
| POST | `/api/rooms/{room}/devices/{device}/enable` | Owner only. Idempotently enables the device. |
| POST | `/api/rooms/{room}/devices/{device}/disable` | Owner only. Idempotently disables the device. |

Device serial numbers are trimmed and normalized to uppercase. A room owner can only attach a device that already exists in the device catalog; unknown serial numbers are never created automatically. Attaching, transferring, removing, or releasing a room resets `is_enabled` to `true`.

## Error Codes

| Code | Meaning |
| --- | --- |
| `ROOM_LAST_OWNER_REQUIRED` | Operation would leave the room without an owner. |
| `ROOM_MEMBER_ALREADY_EXISTS` | User is already a member of the target room. |
| `ROOM_MEMBER_NOT_FOUND` | User is not a member of the target room. |
| `ROOM_INVITATION_ALREADY_PENDING` | A pending invitation already exists for the same room and invitee. |
| `ROOM_INVITATION_NOT_PENDING` | Invitation has already been handled. |
| `ROOM_JOIN_REQUEST_ALREADY_PENDING` | A pending join request already exists for the same room and requester. |
| `ROOM_JOIN_REQUEST_NOT_PENDING` | Join request has already been handled. |
| `ROOM_JOIN_REQUEST_NOT_ACTIVE` | Join request can no longer be cancelled. |
| `ROOM_JOIN_REQUEST_NOT_IGNORED` | Only ignored requests can be restored. |
| `ROOM_OWNER_CANNOT_REMOVE_SELF` | Owners must use the leave endpoint to remove themselves. |
| `ROOM_OWNER_REQUIRED` | The operation requires owner permission. |
| `DEVICE_NOT_FOUND` | Serial number does not match a registered device. |
| `DEVICE_SECRET_INVALID` | Device hidden secret does not match. |
| `DEVICE_LOCKED` | Locked device cannot be transferred. |
| `DEVICE_ALREADY_IN_THIS_ROOM` | Device already belongs to the target room. |
| `DEVICE_NOT_IN_ROOM` | Device is not attached to the target room. |
| `DEVICE_MUST_UNLOCK_BEFORE_REMOVE` | Locked device must be unlocked before removal. |
