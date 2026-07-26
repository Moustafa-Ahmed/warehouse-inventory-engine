<?php

namespace App\Enums\Operations;

enum Type: string
{
    case ReceiveStock = 'receive_stock';
    case AdjustInventory = 'adjust_inventory';
    case TransferStock = 'transfer_stock';
    case CreateOrder = 'create_order';
    case ReserveOrderItem = 'reserve_order_item';
    case ReleaseReservation = 'release_reservation';
    case EditOrderItemQuantity = 'edit_order_item_quantity';
    case ConfirmReservation = 'confirm_reservation';
    case ExpireReservation = 'expire_reservation';
    case PickReservation = 'pick_reservation';
    case ReturnPickedInventory = 'return_picked_inventory';
    case PackReservation = 'pack_reservation';
}
