<?php

/*
 * This file is part of the DigiDoc package.
 *
 * (c) Kristen Gilden <kristen.gilden@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KG\DigiDoc\Exception;

/**
 * Same as \RuntimeException, but extends DigiDocException to ease catching all
 * exceptions thrown by this package.
 */
class RuntimeException extends DigiDocException
{

}
