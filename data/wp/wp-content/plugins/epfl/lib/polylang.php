<?php
/* Copyright © 2020 École Polytechnique Fédérale de Lausanne, Switzerland */
/* All Rights Reserved, except as stated in the LICENSE file. */

/**
 * Support code for Polylang interoperability
 *
 * This module defines classes (actually there is only one at the
 * moment) that inherit from Polylang classes. Therefore, one should
 * not attempt to load it if Polylang is not loaded.
 */

namespace EPFL\Polylang;

/**
 * Trivial subclass of @link PLL_Choose_Lang
 *
 * @link PLL_Choose_Lang is a class defined by the Polylang module,
 * which is abstract for no good reason
 */
class PLL_Choose_Lang extends \PLL_Choose_Lang {}
