<?php

class APIException extends Exception {
}

class SignatureError extends APIException {
}
class InputError extends APIException {
}
class DatabaseError extends APIException {
}
class ConflictError extends APIException {
}
class TimestampError extends APIException {
}

// user status
class UserLookupError extends APIException {
}
class UserStatusError extends APIException {
}
class UserPrivsError extends APIException {
}

class ProbeLookupError extends APIException {
}
class ProbeStateError extends APIException {
}

class UrlLookupError extends APIException {
}
class ContactLookupError extends APIException {
}

class IpLookupError extends APIException {}

class IspLookupError extends APIException{}
class ConfigLoadError extends APIException{}

class BadUrlError extends APIException{}

class TokenLookupError extends APIException {}
class InvalidTokenError extends APIException {}

class InvalidSortError extends APIException {}
