# Device Management API

All endpoints require the authenticated management session middleware:

```text
auth
manage.authenticated
permission:manage.access
```

Device secrets and `secret_hash` are never returned.

## Permissions

| Permission | Purpose |
| --- | --- |
| `manage.devices.view` | List devices and view devices assigned to a room. |
| `manage.devices.detail` | View one device and its transfer history. |
| `manage.devices.create` | Create a device catalog record. |

`service_manager` receives view/detail. `system_admin` receives all three.

## Endpoints

| Method | Path | Permission | Notes |
| --- | --- | --- | --- |
| GET | `/manage/api/devices` | `manage.devices.view` | Search, filter and paginate all devices. |
| GET | `/manage/api/devices/{serial_number}` | `manage.devices.detail` | Device detail and transfer logs, newest first, 20 per page. |
| POST | `/manage/api/devices` | `manage.devices.create` | Create an unassigned device. |
| GET | `/manage/api/rooms/{room_public_id}/devices` | `manage.rooms.detail` and `manage.devices.view` | Paginated devices in one room. |

## Create Device

```json
{
  "serial_number": "DEVICE-000001",
  "secret": "physical-device-secret",
  "secret_confirmation": "physical-device-secret"
}
```

The serial number is trimmed and converted to uppercase. New devices have:

```json
{
  "room": null,
  "name": null,
  "is_locked": false,
  "is_enabled": true
}
```

Duplicate normalized serial numbers return:

```json
{
  "message": "Device serial number already exists.",
  "data": null,
  "code": "DEVICE_SERIAL_ALREADY_EXISTS"
}
```

## List Filters

```text
search
room_public_id
assignment_status=all|assigned|unassigned
locked=true|false
enabled=true|false
page
per_page=20|50|100
```

List searches and pagination are not written to `manage_action_logs`. Successful device detail, room-device detail, and device creation operations are audited.

## MySQL Concurrency Verification

The default PHPUnit suite uses SQLite. Run the MySQL-only row-lock test separately with MySQL test environment variables:

```text
php artisan test tests/Integration/MySqlDeviceConcurrencyTest.php --group=mysql
```
