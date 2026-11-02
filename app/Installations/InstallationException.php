<?php

namespace App\Installations;

use RuntimeException;

/** A request that conflicts with the installation's state or the stock: answered as 409. */
class InstallationException extends RuntimeException {}
