<?php

namespace Seoromka\Decimal;

use DomainException;
use Traversable;

class Decimal
{
    public const ZERO = '0';
    public const EXP_MARK = 'e';
    public const RADIX_MARK = '.';

    public $digits;
    public $exponent = 0;
    public $negative = false;

    public static $raw_formatter;
    public static $zero;
    public static $one;

    /**
     * @param mixed $value An integer, float, Decimal or numeric string.
     */
    public function __construct($value = 0)
    {
        if ($value instanceof static) {
            $this->copy($value);
        } else {
            $clean = static::cleanValue($value);
            if ($clean[0] === '-') {
                $this->negative = true;
                $clean = substr($clean, 1);
            }
            // If the value contains an exponent specifier, parse and remove
            // it.
            $clean = strtolower($clean);
            $pos = strrpos($clean, static::EXP_MARK);
            if ($pos !== false) {
                $this->exponent = (int) substr($clean, $pos + 1);
                $clean = substr($clean, 0, $pos);
            }
            // Remove the period and decrease the exponent by one for each
            // digit following the period.
            $pos = strpos($clean, static::RADIX_MARK);
            if ($pos !== false) {
                $clean = substr($clean, 0, $pos) . substr($clean, $pos + 1);
                $this->exponent += ($pos - strlen($clean));
            }
            // Discard leading zeroes.
            $clean = ltrim($clean, static::ZERO);
            // For integer values (non-negative exponents), remove trailing
            // zeroes and increase the exponent by one for each digit removed.
            if ($this->exponent >= 0) {
                $len = strlen($clean);
                $clean = rtrim($clean, static::ZERO);
                $this->exponent += ($len - strlen($clean));
            }

            $this->digits = $clean === '' ? static::ZERO : $clean;
        }
    }

    /**
     * Make this object equal to $source by copying its properties.
     *
     * @param Decimal $source
     */
    public function copy(Decimal $source): void
    {
        $this->digits   = $source->digits;
        $this->exponent = $source->exponent;
        $this->negative = $source->negative;
    }

    /**
     * Return the number of digits after the decimal point required to fully
     * represent this value.
     *
     * @return int
     */
    public function getScale(): int
    {
        if ($this->exponent >= 0) {
            return 0;
        }

        return -$this->exponent;
    }

    /**
     * Compare this Decimal with a value.
     *
     * @param mixed $value
     * @return int -1 if the instance is less than the $value,
     *         0 if the instance is equal to $value, or
     *         1 if the instance is greater than $value.
     */
    public function compare($value): int
    {
        $decimal = static::make($value);
        $scale = max($this->getScale(), $decimal->getScale());

        return bccomp($this, $decimal, $scale);
    }

    /**
     * @param $value
     * @return bool
     */
    public function equals($value): bool
    {
        return ($this->compare($value) === 0);
    }

    /**
     * @param $value
     * @return bool
     */
    public function greaterThan($value): bool
    {
        return ($this->compare($value) > 0);
    }

    /**
     * @param $value
     * @return bool
     */
    public function lessThan($value): bool
    {
        return ($this->compare($value) < 0);
    }

    /**
     * @return bool
     */
    public function isZero(): bool
    {
        return $this->equals(0);
    }

    /**
     * @return bool
     */
    public function positive(): bool
    {
        return $this->greaterThan(0);
    }

    /**
     * @return bool
     */
    public function negative(): bool
    {
        return $this->lessThan(0);
    }

    /**
     * @param $value
     * @return bool
     */
    public function eq($value): bool
    {
        return $this->equals($value);
    }

    /**
     * @param $value
     * @return bool
     */
    public function lt($value): bool
    {
        return $this->lessThan($value);
    }

    /**
     * @param $value
     * @return bool
     */
    public function gt($value): bool
    {
        return $this->greaterThan($value);
    }

    /**
     * @param $value
     * @return bool
     */
    public function ge($value): bool
    {
        return ($this->compare($value) >= 0);
    }

    /**
     * @param $value
     * @return bool
     */
    public function le($value): bool
    {
        return ($this->compare($value) <= 0);
    }

    /*
     * Return the absolute value of this Decimal as a new Decimal.
     */
    public function abs(): self
    {
        $result = new static($this);
        $result->negative = false;

        return $result;
    }

    /*
     * Return the negation of this Decimal as a new Decimal.
     */
    public function negation(): self
    {
        $result = new static($this);
        $result->negative = ! $this->negative;

        return $result;
    }

    /*
     * Add $value to this Decimal and return the sum as a new Decimal.
     */
    public function add($value, $scale = null): self
    {
        $decimal = static::make($value);
        $scale = static::resultScale($this, $decimal, $scale);

        return new static(bcadd($this, $decimal, $scale));
    }

    /*
     * Subtract $value from this Decimal and return the difference as a new
     * Decimal.
     */
    public function subtract($value, $scale = null): self
    {
        $decimal = static::make($value);
        $scale = static::resultScale($this, $decimal, $scale);

        return new static(bcsub($this, $decimal, $scale));
    }

    /**
     * @param $value
     * @param null $scale
     * @return Decimal
     */
    public function sub($value, $scale = null): self
    {
        return $this->subtract($value, $scale);
    }

    /*
     * Multiply this Decimal by $value and return the product as a new Decimal.
     */
    public function multiply($value, $scale = null): self
    {
        $decimal = static::make($value);

        if (! static::scaleValid($scale)) {
            $scale = $this->getScale() + $decimal->getScale();
        }

        return new static(bcmul($this, $decimal, $scale));
    }

    /**
     * @param $value
     * @param null $scale
     * @return Decimal
     */
    public function mul($value, $scale = null): self
    {
        return $this->multiply($value, $scale);
    }

    /**
     * Divide this Decimal by $value and return the quotient as a new Decimal.
     *
     * @param $value
     * @param null $scale
     * @return Decimal
     */
    public function divide($value, $scale = null): self
    {
        $decimal = static::make($value);
        if ($decimal->isZero()) {
            throw new DomainException('Cannot divide by zero.');
        }

        $scale = static::resultScale($this, $decimal, $scale);

        return new static(bcdiv($this, $decimal, $scale));
    }

    /**
     * @param $value
     * @param null $scale
     * @return Decimal
     */
    public function div($value, $scale = null): self
    {
        return $this->divide($value, $scale);
    }

    /*
     * Return the inverse (1/x) of this Decimal as a new Decimal.
     *
     * The default scale of the division will be equal to the exponent of this
     * Decimal plus one, if it is positive, otherwise it will be zero.
     */
    public function inverse($scale = null): self
    {
        if (! static::scaleValid($scale)) {
            $scale = max(0, $this->exponent + 1);
        }

        return static::one()->divide($this, $scale);
    }

    /*
     * Increase this Decimal in-place by the given argument(s).
     *
     * Traversable arguments are processed recursively.
     */
    public function increase(): void
    {
        $args = func_get_args();

        foreach ($args as $arg) {
            if (is_array($arg) || $arg instanceof Traversable) {
                foreach ($arg as $element) {
                    $this->increase($element);
                }
            } else {
                $this->copy($this->add($arg));
            }
        }
    }

    /*
     * Decrease this Decimal in-place by the given argument(s).
     *
     * Traversable arguments are processed recursively.
     */
    public function decrease(): void
    {
        $args = func_get_args();

        foreach ($args as $arg) {
            if (is_array($arg) || $arg instanceof Traversable) {
                foreach ($arg as $element) {
                    $this->decrease($element);
                }
            } else {
                $this->copy($this->sub($arg));
            }
        }
    }

    /*
     * Flip the sign of this Decimal, and return whether the result is
     * negative.
     *
     * @return bool
     */
    public function negate(): bool
    {
        $this->negative = ! $this->negative;
        return $this->negative;
    }

    /**
     * Return a new Decimal which represents this value in its canonical form.
     *
     * The canonical form is the form that uses the minimum possible number of
     * digits without any loss of precision to the value.
     *
     * A zero value will always be returned as a positive Decimal -- it is
     * possible to represent "negative zero" using a Decimal object, but the
     * treatment of zero in this library is unsigned, and the canonical
     * representation of zero is always positive zero.
     *
     * @return Decimal
     */
    public function compress(): self
    {
        $result = clone $this;
        $len = strlen($result->digits);
        $result->digits = ltrim($result->digits, static::ZERO);
        $newlen = strlen($result->digits);
        if ($newlen > 0) {
            $result->exponent -= $len - $newlen;
        } else {
            $result->digits = static::ZERO;
            $result->exponent = 0;
            $result->negative = false;
            return $result;
        }

        $result->digits = rtrim($result->digits, static::ZERO);
        $result->exponent += $newlen - strlen($result->digits);

        return $result;
    }

    /**
     * Return a new Decimal which expresses this value at the given exponent.
     *
     * If this Decimal cannot be fully expressed using the target exponent,
     * round the result using $method, which has the same meaning as in PHP's
     * built-in round function.
     *
     * @param int $exponent The target exponent.
     * @param int $method The rounding method to use, if necessary.
     * @return Decimal
     */
    public function quantize($exponent, $method = PHP_ROUND_HALF_UP): self
    {
        $result = $this->compress();
        $count = $result->exponent - $exponent;
        if ($exponent < $result->exponent) {
            $result->digits .= static::zeroes($count);
            $result->exponent = $exponent;
        } elseif($exponent > $result->exponent) {
            if ($result->exponent < 0) {
                if (strlen($result->digits) <= abs($count)) {
                    $prev_even = true;
                } else {
                    $prev = (int) substr($result->digits, $count - 1, 1);
                    $prev_even = ($prev % 2 == 0);
                }

                $roundoff = new static;
                if ($method == PHP_ROUND_HALF_DOWN ||
                    ($method == PHP_ROUND_HALF_EVEN && $prev_even) ||
                    ($method == PHP_ROUND_HALF_ODD && !$prev_even)) {
                    $roundoff->digits = '4';
                } else {
                    $roundoff->digits = '5';
                }
                $roundoff->exponent = ($exponent - 1);
                $roundoff->negative = $result->negative;
                $result->increase($roundoff);
                $result->digits = substr($result->digits, 0, $count);
                if (strlen($result->digits) === 0) {
                    $result->digits = static::ZERO;
                }
            } else {
                $result->digits = static::zeroes(-$count) . $result->digits;
            }
            $result->exponent = $exponent;
        }

        return $result;
    }

    /**
     * Return a new Decimal from this instance which has been rounded.
     *
     * @param int $places Number of decimal places to round to.
     * @param int $method The method to use for rounding, per PHP's built-in
     *         round() function.
     * @return Decimal
     */
    public function round($places, $method = PHP_ROUND_HALF_UP): self
    {
        return $this->quantize(min(0, -$places), $method);
    }

    /**
     * Return a basic string representation of this Decimal.
     *
     * The output of this method is guaranteed to yield exactly the same value
     * if fed back into the Decimal constructor.
     *
     * The format of the string is an optional negative sign marker, followed
     * by one or more digits, followed optionally by the radix mark and one or
     * more digits.
     *
     * @return string
     */
    public function __toString(): string
    {
        if (! static::$raw_formatter instanceof DecimalFormatter) {
            static::$raw_formatter = new DecimalFormatter(null, '', static::RADIX_MARK);
        }

        return static::$raw_formatter->format($this);
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        return (string) $this;
    }

    /**
     * Return some approximation of this Decimal as a PHP native float.
     *
     * Due to the nature of binary floating-point, some valid values of Decimal
     * will not have any finite representation as a float, and some valid
     * values of Decimal will be out of the range handled by floats.  You have
     * been warned.
     *
     * @return float
     */
    public function toFloat(): float
    {
        return (float) (string) $this;
    }

    /**
     * Return this Decimal formatted as a string.
     *
     * @param int $places Number of fractional digits to show.
     *         The value will be rounded as necessary to accomodate this
     *         setting, using the default rounding method.  If $places is null,
     *         the result will use as many places as required to show the value
     *         in full.
     * @param string $grouping String to use as a thousands separator.
     * @param string $radix_mark String to separate the integer part from
     *         the fractional part, also known as a 'decimal point'.
     * @return string
     */
    public function format($places = null, $grouping = '', $radix_mark = self::RADIX_MARK): string
    {
        $f = new DecimalFormatter($places, $grouping, $radix_mark);
        return $f->format($this);
    }

    /**
     * @param int $places
     * @return string
     */
    public function toFixed(int $places = 2): string
    {
        return $this->format($places);
    }

    /**
     * Return a Decimal instance from the given value.
     *
     * If the value is already a Decimal instance, then return it unmodified.
     * Otherwise, create a new Decimal instance from the given value and return
     * it.
     *
     * @param mixed $value
     * @return self
     */
    public static function make($value)
    {
        return $value instanceof static ? $value : new static($value);
    }

    /**
     * Return the given number as a string with irrelevant characters removed.
     *
     * All characters other than digits, hyphen, the radix marker and the
     * exponent marker are removed entirely.
     *
     * @param mixed $value
     * @return string
     * @throw \DomainException if the value is not a valid numeric
     *         representation.
     */
    public static function cleanValue($value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $chars = '\d' . static::RADIX_MARK . static::EXP_MARK . '-';
        $clean = preg_replace("/[^$chars]/i", '', $value);
        $clean = rtrim($clean, static::RADIX_MARK);
        $radix = '[' . static::RADIX_MARK . ']';
        $pattern = '/^-?(?:\d+(?:' . $radix . '\d*)?|' .
            $radix . '\d+)(?:' . static::EXP_MARK . '-?\d*)?$/i';

        if (! preg_match($pattern, $clean)) {
            throw new DomainException(
                "Invalid Decimal value '$value'; " .
                'must contain either an integer part, a fractional ' .
                "part, or both, separated by '" . static::RADIX_MARK . "', " .
                'optionally preceded by a sign specifier, optionally ' .
                'followed by ' . static::EXP_MARK . 'and an integer exponent.');
        }

        return $clean;
    }

    /**
     * Return the greatest of the arguments.
     *
     * @param mixed,...
     * @return Decimal
     */
    public static function max(): self
    {
        $args = func_get_args();
        $result = null;

        foreach ($args as $arg) {
            $dec = static::make($arg);
            if ($result === null || $result->lt($dec)) {
                $result = $dec;
            }
        }

        return $result;
    }

    /**
     * Return the least of the arguments.
     *
     * @param mixed,...
     * @return Decimal
     */
    public static function min(): self
    {
        $args = func_get_args();
        $result = null;

        foreach ($args as $arg) {
            $dec = static::make($arg);
            if ($result === null || $result->gt($dec)) {
                $result = $dec;
            }
        }

        return $result;
    }

    /**
     * Return whether $scale is valid as a decimal operation scale.
     *
     * @param int $scale
     * @return bool
     */
    public static function scaleValid($scale): bool
    {
        return (is_int($scale) && $scale >= 0);
    }

    /**
     * Return zero as a Decimal.
     *
     * @return Decimal
     */
    public static function zero(): self
    {
        if (! static::$zero instanceof self) {
            static::$zero = new static(0);
        }

        return static::$zero;
    }

    /**
     * Return the value one as a Decimal.
     *
     * @return Decimal
     */
    public static function one(): self
    {
        if (! static::$one instanceof self) {
            static::$one = new static(1);
        }

        return static::$one;
    }

    /*
     * Return an appropriate scale for an arithmetic operation on two Decimals.
     *
     * If $scale is specified and is a valid positive integer, return it.
     * Otherwise, return the higher of the scales of the operands.
     *
     * @param Decimal $a
     * @param Decimal $b
     * @param int|null $scale
     * @return int
     */
    public static function resultScale(Decimal $a, Decimal $b, $scale = null)
    {
        if (! static::scaleValid($scale)) {
            $scale = max($a->getScale(), $b->getScale());
        }

        return $scale;
    }

    /**
     * Return a string of zeroes of length $length.
     *
     * @param int $length
     * @return string
     */
    public static function zeroes($length): string
    {
        return str_repeat(static::ZERO, $length);
    }
}
