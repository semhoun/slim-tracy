<?php

declare(strict_types=1);

namespace SlimTracy;

/** Supported browser console methods. */
enum ConsoleLevel: string
{
    case Log = 'log';
    case Info = 'info';
    case Warn = 'warn';
    case Error = 'error';
}
