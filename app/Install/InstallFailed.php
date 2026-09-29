<?php

namespace App\Install;

use RuntimeException;

/** Raised when the install cannot finish. The message is safe to show to the person installing. */
final class InstallFailed extends RuntimeException {}
