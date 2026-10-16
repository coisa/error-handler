<?php

// phpcs:ignoreFile -- Intentionally invalid inheritance in one bounded child fixture.
// Intentionally invalid inheritance, required only by bounded child fixtures.
/**
 * Defines the final class involved in the synthetic compile-time failure.
 */
final class FireFinalClass {}
/**
 * Intentionally violates final inheritance to produce E_COMPILE_ERROR.
 */
class FireInvalidSubclass extends FireFinalClass {}
