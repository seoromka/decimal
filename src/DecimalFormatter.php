<?php

namespace Seoromka\Decimal;

class DecimalFormatter
{
    public $places;
    public $grouping;
    public $radix_mark;

    /**
     * DecimalFormatter constructor.
     * @param null $places
     * @param string $grouping
     * @param string $radix_mark
     */
    public function __construct($places = null, $grouping = '', $radix_mark = Decimal::RADIX_MARK)
    {
        $this->places = $places;
        $this->grouping = $grouping;
        $this->radix_mark = $radix_mark;
    }

    /**
     * @param $decimal
     * @return string
     */
    public function format($decimal): string
    {
        if ($decimal === '' || $decimal === null) {
            $decimal = 0;
        }

        if ($decimal instanceof Decimal) {
            $decimal = $decimal->compress();
        } else {
            $decimal = new Decimal($decimal);
        }

        if ($this->places !== null && $this->places != $decimal->getScale()) {
            $decimal = $decimal->round($this->places);
        }

        if ($decimal->exponent >= 0) {
            $fill = Decimal::zeroes($decimal->exponent);
            $intpart = $decimal->digits . $fill;
            $fracpart = '';
        } else {
            $intpart = substr($decimal->digits, 0, $decimal->exponent);
            $fracpart = substr($decimal->digits, $decimal->exponent);
            $len = strlen($fracpart);
            $scale = $decimal->getScale();
            if ($len < $scale) {
                $fracpart = Decimal::zeroes($scale - $len) . $fracpart;
            }
        }
        if ($intpart === '') {
            $intpart = Decimal::ZERO;
        }

        $grouplen = strlen($this->grouping);

        if ($grouplen > 0) {
            $strlen = strlen($intpart);
            for ($i = 3; $i < $strlen; $i += 3 + $grouplen) {
                $intpart = substr_replace($intpart, $this->grouping, -$i, 0);
            }
        }

        $result = '';
        if ($decimal->negative) {
            $result = '-';
        }

        $result .= $intpart;
        if (strlen($fracpart) > 0) {
            $result .= $this->radix_mark . $fracpart;
        }

        return $result;
    }
}
