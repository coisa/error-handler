<?php

interface ExceptionHandlerInterface
{
    public function handleException(\Throwable $exception): void;
}
