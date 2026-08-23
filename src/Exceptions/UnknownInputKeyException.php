<?php

declare(strict_types=1);

namespace Axiom\DataForge\Exceptions;

/**
 * Raised when input contains keys outside the DTO's accepted input surface,
 * i.e. strict-input violations.
 *
 * Unlike ordinary validation errors (which are keyed by canonical property
 * names and translated to input keys at the root boundary), this exception's
 * keys are already expressed in the caller's input namespace. The error-key
 * translation boundary must therefore pass its leaf keys through verbatim
 * instead of mapping them as canonical property names.
 */
final class UnknownInputKeyException extends ValidationException {}
