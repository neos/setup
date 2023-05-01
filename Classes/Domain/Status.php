<?php

namespace Neos\Setup\Domain;

enum Status: string
{
    case OK = 'OK';
    case ERROR = 'ERROR';
    case WARNING = 'WARNING';
    case UNKNOWN = 'UNKNOWN';
}
