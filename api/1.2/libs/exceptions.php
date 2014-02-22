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

class ProbeLookupError extends APIException {
}
