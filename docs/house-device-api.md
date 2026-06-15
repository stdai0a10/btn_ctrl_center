# House Device Management API

All endpoints below require the existing authenticated API session middleware.

Error responses use the shared shape:

```json
{
  "message": "Cannot remove the last owner.",
  "data": null,
  "code": "HOUSE_LAST_OWNER_REQUIRED"
}
```

## Houses

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/api/houses` | List houses for the current user. |
| POST | `/api/houses` | Create a house and attach the creator as `owner`. Body: `name`. |
| GET | `/api/houses/{house}` | Show house, role, and members. Uses house `public_id`. |
| PATCH | `/api/houses/{house}` | Owner only. Body: `name`. |
| DELETE | `/api/houses/{house}` | Owner only. Clears devices and cancels pending invitations/requests. |

## Members

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/api/houses/{house}/members` | House members only. |
| DELETE | `/api/houses/{house}/members/{user}` | Owner only. `{user}` is user `public_id`; owners cannot remove themselves. |
| POST | `/api/houses/{house}/leave` | Current member leaves. Last owner alone deletes the house. |
| PATCH | `/api/houses/{house}/members/{user}/role` | Owner only. Body: `role` as `owner` or `resident`. |

## Invitations

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/api/house-invitations` | Returns `received` and owner-visible `sent` invitations. |
| POST | `/api/houses/{house}/invitations` | Owner only. Body: `invitee_public_id`. |
| POST | `/api/house-invitations/{invitation}/accept` | Invitee only. Adds invitee as `resident`. |
| POST | `/api/house-invitations/{invitation}/ignore` | Invitee only. |
| POST | `/api/house-invitations/{invitation}/cancel` | House owner only. |

## Join Requests

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/api/house-join-requests` | Returns current user's `sent` requests and owner-visible `received` requests. |
| POST | `/api/houses/{house}/join-requests` | Non-member asks to join. |
| POST | `/api/house-join-requests/{joinRequest}/accept` | House owner only. Adds requester as `resident`. |
| POST | `/api/house-join-requests/{joinRequest}/ignore` | House owner only. |
| POST | `/api/house-join-requests/{joinRequest}/cancel` | Requester only. |

## Devices

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/api/houses/{house}/devices` | House members only. Secret is never returned. |
| POST | `/api/houses/{house}/devices` | Owner only. Body: `serial_number`, `secret`, optional `name`, optional `lock`. Can transfer unlocked devices. |
| PATCH | `/api/houses/{house}/devices/{device}` | Owner only. Body: `name`. |
| DELETE | `/api/houses/{house}/devices/{device}` | Owner only. Device must be unlocked. |
| POST | `/api/houses/{house}/devices/{device}/lock` | Owner only. |
| POST | `/api/houses/{house}/devices/{device}/unlock` | Owner only. |

## Error Codes

| Code | Meaning |
| --- | --- |
| `HOUSE_LAST_OWNER_REQUIRED` | Operation would leave the house without an owner. |
| `HOUSE_MEMBER_ALREADY_EXISTS` | User is already a member of the target house. |
| `HOUSE_MEMBER_NOT_FOUND` | User is not a member of the target house. |
| `HOUSE_INVITATION_ALREADY_PENDING` | A pending invitation already exists for the same house and invitee. |
| `HOUSE_INVITATION_NOT_PENDING` | Invitation has already been handled. |
| `HOUSE_JOIN_REQUEST_ALREADY_PENDING` | A pending join request already exists for the same house and requester. |
| `HOUSE_JOIN_REQUEST_NOT_PENDING` | Join request has already been handled. |
| `HOUSE_OWNER_CANNOT_REMOVE_SELF` | Owners must use the leave endpoint to remove themselves. |
| `HOUSE_OWNER_REQUIRED` | The operation requires owner permission. |
| `DEVICE_NOT_FOUND` | Serial number does not match a registered device. |
| `DEVICE_SECRET_INVALID` | Device hidden secret does not match. |
| `DEVICE_LOCKED` | Locked device cannot be transferred. |
| `DEVICE_ALREADY_IN_THIS_HOUSE` | Device already belongs to the target house. |
| `DEVICE_NOT_IN_HOUSE` | Device is not attached to the target house. |
| `DEVICE_MUST_UNLOCK_BEFORE_REMOVE` | Locked device must be unlocked before removal. |
