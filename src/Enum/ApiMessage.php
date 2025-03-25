<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * API Message Enum
 * 
 * Standard messages utilisés dans les réponses API
 */
enum ApiMessage: string
{
    case RESOURCE_FETCHED = 'Resource fetched successfully';
    case RESOURCE_CREATED = 'Resource successfully created';
    case RESOURCE_UPDATED = 'Resource successfully updated';
    case RESOURCE_DELETED = 'Resource successfully deleted';
    case RESOURCE_NOT_FOUND = 'Resource not found';

    case ACCESS_DENIED = 'Access denied';

    case EMAIL_ALREADY_USED = 'A user with this email already exists';
    case ACCOUNT_LOCKED = 'Account is locked';
    
    case INVALID_CREDENTIALS = 'Invalid credentials';
    case INVALID_DATA = 'Invalid data';

    case FORBIDDEN = 'Forbidden';
    case MISSING_FIELDS = 'Required fields missing';
    case INVALID_PASSWORD_FORMAT = 'Invalid password format';
    case INTERNAL_SERVER_ERROR = 'Internal server error';
    
    case USER_NOT_FOUND = 'User not found';
    
    case OTP_SENT = 'OTP sent successfully';
    case OTP_VERIFIED = 'OTP verified successfully';
    case INVALID_OTP = 'Invalid OTP';
    case OTP_EXPIRED = 'OTP expired';
    case GOOGLE_AUTHENTICATION_FAILED = 'Google authentication failed';
    case GOOGLE_VERIFICATION_FAILED = 'Google verification failed';
    case GOOGLE_ACCOUNT_NEED_PASSWORD = 'set_password';
    case REGISTRATION_FAILED = 'Registration failed';
    case INVALID_TOKEN = 'Invalid token';

    /**
     * Obtient le libellé du message
     *
     * @return string Le texte du message
     */
    public function getLabel(): string
    {
        return $this->value;
    }
}
