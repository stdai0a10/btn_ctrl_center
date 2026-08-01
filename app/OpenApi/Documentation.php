<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * PSR-4 autoload anchor for the OpenAPI component declarations in this file.
 */
final class Documentation {}

#[OA\Info(
    version: '1.0.0',
    title: 'Button Control Center API',
    description: 'HTTP API for account, room, button, device runtime, and management operations.',
)]
#[OA\Server(url: '/', description: 'Current application host')]
#[OA\SecurityScheme(
    securityScheme: 'sessionCookie',
    type: 'apiKey',
    name: 'laravel_session',
    in: 'cookie',
    description: 'Laravel session authentication. State-changing browser requests also require the Sanctum CSRF cookie and X-XSRF-TOKEN header.',
)]
#[OA\SecurityScheme(
    securityScheme: 'deviceBearer',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Device long-lived or short-lived JWT, according to the endpoint.',
)]
final class Definition {}

#[OA\Schema(
    schema: 'ApiResponse',
    type: 'object',
    required: ['message', 'data'],
    properties: [
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'data', description: 'Endpoint-specific response payload.', nullable: true),
    ],
)]
final class ApiResponseSchema {}

#[OA\Schema(
    schema: 'ErrorResponse',
    type: 'object',
    required: ['message', 'data'],
    properties: [
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'data', description: 'Validation errors or endpoint-specific error details.', nullable: true),
        new OA\Property(property: 'code', type: 'string', nullable: true),
    ],
)]
final class ErrorResponseSchema {}

#[OA\Response(
    response: 'Success',
    description: 'Successful API response.',
    content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'),
)]
#[OA\Response(
    response: 'Created',
    description: 'Resource created successfully.',
    content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'),
)]
#[OA\Response(
    response: 'Error',
    description: 'Request failed. The HTTP status identifies validation, authentication, authorization, missing-resource, or server errors.',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
)]
#[OA\Parameter(
    parameter: 'PathRoom',
    name: 'room',
    in: 'path',
    required: true,
    description: 'Room public identifier.',
    schema: new OA\Schema(type: 'string'),
)]
#[OA\Parameter(
    parameter: 'PathUser',
    name: 'user',
    in: 'path',
    required: true,
    description: 'User public identifier.',
    schema: new OA\Schema(type: 'string'),
)]
#[OA\Parameter(
    parameter: 'PathDevice',
    name: 'device',
    in: 'path',
    required: true,
    description: 'Device route identifier.',
    schema: new OA\Schema(type: 'string'),
)]
#[OA\Parameter(
    parameter: 'PathSerialNumber',
    name: 'serial_number',
    in: 'path',
    required: true,
    description: 'Device serial number.',
    schema: new OA\Schema(type: 'string', maxLength: 100),
)]
#[OA\Parameter(
    parameter: 'PathButtonPage',
    name: 'buttonPage',
    in: 'path',
    required: true,
    description: 'Button page public identifier.',
    schema: new OA\Schema(type: 'string', maxLength: 20),
)]
#[OA\Parameter(
    parameter: 'PathButtonActionJob',
    name: 'buttonActionJob',
    in: 'path',
    required: true,
    description: 'Button action job public identifier.',
    schema: new OA\Schema(type: 'string', maxLength: 20),
)]
#[OA\Parameter(
    parameter: 'PathDeviceJob',
    name: 'button_action_job_public_id',
    in: 'path',
    required: true,
    description: 'Button action job public identifier.',
    schema: new OA\Schema(type: 'string', maxLength: 20),
)]
#[OA\Parameter(
    parameter: 'PathInvitation',
    name: 'invitation',
    in: 'path',
    required: true,
    description: 'Room invitation identifier.',
    schema: new OA\Schema(type: 'integer', minimum: 1),
)]
#[OA\Parameter(
    parameter: 'PathJoinRequest',
    name: 'joinRequest',
    in: 'path',
    required: true,
    description: 'Room join request identifier.',
    schema: new OA\Schema(type: 'integer', minimum: 1),
)]
#[OA\Parameter(
    parameter: 'PathUserPublicId',
    name: 'user_public_id',
    in: 'path',
    required: true,
    description: 'User public identifier.',
    schema: new OA\Schema(type: 'string', maxLength: 20),
)]
#[OA\Parameter(
    parameter: 'PathRoomPublicId',
    name: 'room_public_id',
    in: 'path',
    required: true,
    description: 'Room public identifier.',
    schema: new OA\Schema(type: 'string', maxLength: 26),
)]
#[OA\Parameter(
    parameter: 'PathProductPublicId',
    name: 'product_public_id',
    in: 'path',
    required: true,
    description: 'Product public identifier.',
    schema: new OA\Schema(type: 'string', maxLength: 20),
)]
#[OA\Parameter(
    parameter: 'PathProductFunctionCode',
    name: 'code',
    in: 'path',
    required: true,
    description: 'Product function code.',
    schema: new OA\Schema(type: 'string', maxLength: 20),
)]
#[OA\Parameter(
    parameter: 'QuerySearch',
    name: 'search',
    in: 'query',
    schema: new OA\Schema(type: 'string', maxLength: 255),
)]
#[OA\Parameter(
    parameter: 'QueryPage',
    name: 'page',
    in: 'query',
    schema: new OA\Schema(type: 'integer', minimum: 1),
)]
#[OA\Parameter(
    parameter: 'QueryPerPage',
    name: 'per_page',
    in: 'query',
    schema: new OA\Schema(type: 'integer', enum: [20, 50, 100]),
)]
final class SharedComponents {}

#[OA\Schema(
    schema: 'EmailRegistrationRequest',
    type: 'object',
    required: ['email', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 12),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', minLength: 12),
    ],
)]
final class EmailRegistrationRequestSchema {}

#[OA\Schema(
    schema: 'EmailLoginRequest',
    type: 'object',
    required: ['email', 'password'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255),
        new OA\Property(property: 'password', type: 'string', format: 'password'),
        new OA\Property(property: 'redirect', type: 'string', maxLength: 2048, nullable: true),
    ],
)]
final class EmailLoginRequestSchema {}

#[OA\Schema(
    schema: 'EmailRequest',
    type: 'object',
    required: ['email'],
    properties: [new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255)],
)]
final class EmailRequestSchema {}

#[OA\Schema(
    schema: 'PasswordResetRequest',
    type: 'object',
    required: ['token', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'token', type: 'string', minLength: 64, maxLength: 64),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 12),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', minLength: 12),
    ],
)]
final class PasswordResetRequestSchema {}

#[OA\Schema(
    schema: 'LiffLoginRequest',
    type: 'object',
    required: ['access_token'],
    properties: [new OA\Property(property: 'access_token', type: 'string')],
)]
final class LiffLoginRequestSchema {}

#[OA\Schema(
    schema: 'ProfileUpdateRequest',
    type: 'object',
    properties: [new OA\Property(property: 'name', type: 'string', maxLength: 100, nullable: true)],
)]
final class ProfileUpdateRequestSchema {}

#[OA\Schema(
    schema: 'PasswordUpdateRequest',
    type: 'object',
    required: ['password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'current_password', type: 'string', format: 'password', nullable: true),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 12),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', minLength: 12),
    ],
)]
final class PasswordUpdateRequestSchema {}

#[OA\Schema(
    schema: 'PasswordRequest',
    type: 'object',
    required: ['password'],
    properties: [new OA\Property(property: 'password', type: 'string', format: 'password')],
)]
final class PasswordRequestSchema {}

#[OA\Schema(
    schema: 'RoomRequest',
    type: 'object',
    required: ['name'],
    properties: [new OA\Property(property: 'name', type: 'string', maxLength: 100)],
)]
final class RoomRequestSchema {}

#[OA\Schema(
    schema: 'RoomInvitationRequest',
    type: 'object',
    required: ['invitee_public_id'],
    properties: [new OA\Property(property: 'invitee_public_id', type: 'string')],
)]
final class RoomInvitationRequestSchema {}

#[OA\Schema(
    schema: 'RoomMemberRoleRequest',
    type: 'object',
    required: ['role'],
    properties: [new OA\Property(property: 'role', type: 'string', enum: ['owner', 'resident'])],
)]
final class RoomMemberRoleRequestSchema {}

#[OA\Schema(
    schema: 'RoomDeviceCreateRequest',
    type: 'object',
    required: ['serial_number', 'secret'],
    properties: [
        new OA\Property(property: 'serial_number', type: 'string', maxLength: 100),
        new OA\Property(property: 'secret', type: 'string', format: 'password', maxLength: 255),
        new OA\Property(property: 'name', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'lock', type: 'boolean', nullable: true),
    ],
)]
final class RoomDeviceCreateRequestSchema {}

#[OA\Schema(
    schema: 'RoomDeviceUpdateRequest',
    type: 'object',
    properties: [new OA\Property(property: 'name', type: 'string', maxLength: 100, nullable: true)],
)]
final class RoomDeviceUpdateRequestSchema {}

#[OA\Schema(
    schema: 'DeviceLongTokenRequest',
    type: 'object',
    required: ['serial_number', 'secret'],
    properties: [
        new OA\Property(property: 'serial_number', type: 'string', maxLength: 100),
        new OA\Property(property: 'secret', type: 'string', format: 'password', maxLength: 255),
        new OA\Property(property: 'name', type: 'string', maxLength: 255, nullable: true),
        new OA\Property(property: 'version', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'capabilities', type: 'array', nullable: true, items: new OA\Items(type: 'string', maxLength: 100)),
    ],
)]
final class DeviceLongTokenRequestSchema {}

#[OA\Schema(
    schema: 'DevicePollRequest',
    type: 'object',
    properties: [
        new OA\Property(property: 'status', type: 'string', maxLength: 50, nullable: true),
        new OA\Property(property: 'current_job_id', type: 'string', maxLength: 20, nullable: true),
    ],
)]
final class DevicePollRequestSchema {}

#[OA\Schema(
    schema: 'DeviceJobProgressRequest',
    type: 'object',
    required: ['progress'],
    properties: [
        new OA\Property(property: 'progress', type: 'integer', minimum: 0, maximum: 100),
        new OA\Property(property: 'message', type: 'string', maxLength: 255, nullable: true),
        new OA\Property(property: 'status', type: 'string', maxLength: 50, nullable: true),
    ],
)]
final class DeviceJobProgressRequestSchema {}

#[OA\Schema(
    schema: 'DeviceJobCompleteRequest',
    type: 'object',
    required: ['status'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['succeeded', 'failed']),
        new OA\Property(property: 'result', type: 'object', nullable: true, additionalProperties: true),
        new OA\Property(property: 'error_message', type: 'string', maxLength: 2000, nullable: true),
    ],
)]
final class DeviceJobCompleteRequestSchema {}

#[OA\Schema(
    schema: 'ButtonPageRequest',
    type: 'object',
    required: ['name', 'layout_columns'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 100),
        new OA\Property(property: 'layout_columns', type: 'integer', enum: [2, 3, 4, 5]),
    ],
)]
final class ButtonPageRequestSchema {}

#[OA\Schema(
    schema: 'ButtonPageLayoutRequest',
    type: 'object',
    required: ['name', 'layout_columns'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 100),
        new OA\Property(property: 'layout_columns', type: 'integer', enum: [2, 3, 4, 5]),
        new OA\Property(
            property: 'buttons',
            type: 'array',
            maxItems: 100,
            items: new OA\Items(
                type: 'object',
                required: ['device_serial_number', 'product_function_code', 'position', 'shape', 'background_color', 'content_type', 'foreground_color'],
                properties: [
                    new OA\Property(property: 'public_id', type: 'string', maxLength: 20, nullable: true),
                    new OA\Property(property: 'device_serial_number', type: 'string', maxLength: 100),
                    new OA\Property(property: 'product_function_code', type: 'string', maxLength: 20),
                    new OA\Property(property: 'position', type: 'integer', minimum: 0),
                    new OA\Property(property: 'shape', type: 'string', enum: ['rounded_square', 'circle']),
                    new OA\Property(property: 'background_color', type: 'string', pattern: '^#[0-9A-Fa-f]{6}$'),
                    new OA\Property(property: 'content_type', type: 'string', enum: ['icon', 'text']),
                    new OA\Property(property: 'icon_key', type: 'string', maxLength: 50, nullable: true),
                    new OA\Property(property: 'label', type: 'string', maxLength: 100, nullable: true),
                    new OA\Property(property: 'foreground_color', type: 'string', pattern: '^#[0-9A-Fa-f]{6}$'),
                ],
            ),
        ),
    ],
)]
final class ButtonPageLayoutRequestSchema {}

#[OA\Schema(
    schema: 'ButtonPageOrderRequest',
    type: 'object',
    required: ['button_page_public_ids'],
    properties: [
        new OA\Property(property: 'button_page_public_ids', type: 'array', items: new OA\Items(type: 'string', maxLength: 20)),
    ],
)]
final class ButtonPageOrderRequestSchema {}

#[OA\Schema(
    schema: 'ButtonActionRequest',
    type: 'object',
    required: ['button_public_id', 'request_id'],
    properties: [
        new OA\Property(property: 'button_public_id', type: 'string', maxLength: 20),
        new OA\Property(property: 'request_id', type: 'string', maxLength: 100),
    ],
)]
final class ButtonActionRequestSchema {}

#[OA\Schema(
    schema: 'ManageLoginRequest',
    type: 'object',
    required: ['email', 'password'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255),
        new OA\Property(property: 'password', type: 'string', format: 'password'),
    ],
)]
final class ManageLoginRequestSchema {}

#[OA\Schema(
    schema: 'ManageDeviceCreateRequest',
    type: 'object',
    required: ['product_public_id', 'serial_number', 'secret', 'secret_confirmation'],
    properties: [
        new OA\Property(property: 'product_public_id', type: 'string', maxLength: 20),
        new OA\Property(property: 'serial_number', type: 'string', maxLength: 100),
        new OA\Property(property: 'secret', type: 'string', format: 'password', maxLength: 255),
        new OA\Property(property: 'secret_confirmation', type: 'string', format: 'password', maxLength: 255),
    ],
)]
final class ManageDeviceCreateRequestSchema {}

#[OA\Schema(
    schema: 'ProductRequest',
    type: 'object',
    required: ['model_number', 'name'],
    properties: [
        new OA\Property(property: 'model_number', type: 'string', maxLength: 100),
        new OA\Property(property: 'name', type: 'string', maxLength: 255),
    ],
)]
final class ProductRequestSchema {}

#[OA\Schema(
    schema: 'ProductFunctionRequest',
    type: 'object',
    required: ['description'],
    properties: [new OA\Property(property: 'description', type: 'string', maxLength: 255)],
)]
final class ProductFunctionRequestSchema {}

#[OA\Schema(
    schema: 'ServiceManagerBatchRequest',
    type: 'object',
    required: ['user_public_ids'],
    properties: [
        new OA\Property(
            property: 'user_public_ids',
            type: 'array',
            minItems: 1,
            maxItems: 100,
            uniqueItems: true,
            items: new OA\Items(type: 'string'),
        ),
    ],
)]
final class ServiceManagerBatchRequestSchema {}
