<?php

namespace App\Enums;

/**
 * Fields the items table of a league can be sorted by, the values are the `sort` query values.
 */
enum ItemsSortFieldEnum: string
{
    case Name = 'name';
    case Price = 'price';
    case LogReturn = 'logReturn';
    case Residual = 'residual';
    case ResidualMad = 'residualMad';
    case Beta = 'beta';
    case Observations = 'observations';
    case Units = 'units';
    case UnusualRatio = 'unusualRatio';
}
