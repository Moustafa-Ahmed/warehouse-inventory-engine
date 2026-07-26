<?php

namespace App\Enums\Operations;

enum Type: string
{
    case ReceiveStock = 'receive_stock';
    case AdjustInventory = 'adjust_inventory';
    case TransferStock = 'transfer_stock';
}
