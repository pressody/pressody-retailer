<?php
/**
 * Decryption exception for when the wrong encryption key or a modified cipher text was provided.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.10.0
 */

/*
 * This file is part of a Pressody module.
 *
 * This Pressody module is free software: you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation, either version 2 of the License,
 * or (at your option) any later version.
 *
 * This Pressody module is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this Pressody module.
 * If not, see <https://www.gnu.org/licenses/>.
 *
 * Copyright (c) 2021, 2022 Vlad Olaru (vlad@thinkwritecode.com)
 */

declare ( strict_types = 1 );

namespace Pressody\Retailer\Exception;

/**
 * Decryption exception class for when the wrong encryption key or a modified cipher text was provided.
 *
 * @since 0.10.0
 */
class CrypterWrongKeyOrModifiedCiphertextException extends CrypterException {

}
