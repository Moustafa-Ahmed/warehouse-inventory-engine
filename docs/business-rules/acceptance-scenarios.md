# Acceptance Scenarios

These scenarios describe observable behavior. They are evidence candidates, not a requirement for one automated test per scenario. The [Risk-Based Testing Strategy](../implementation-plan/testing-strategy.md) identifies the important risks that receive focused automated tests; secondary behavior may be covered by smoke tests or the walkthrough.

## 1. Concurrent Final-Unit Reservation

**Given** one available unit for a product at a warehouse  
**When** two operations concurrently request that unit  
**Then** one operation allocates it  
**And** the other reports zero allocated and one outstanding  
**And** no bucket becomes negative  
**And** exactly one reserve movement exists.

## 2. Partial Reservation

**Given** an order item requests 10 units and 6 are available  
**When** reservation runs  
**Then** 6 move from available to reserved  
**And** the result reports 10 requested, 6 allocated, and 4 outstanding  
**And** the item is partially allocated.

## 3. Automatic Backorder Allocation

**Given** an order item has 4 outstanding units  
**When** at least 4 new units are received at its selected warehouse  
**Then** an after-commit job attempts allocation  
**And** the scheduled allocator can recover the work  
**And** the oldest eligible outstanding item receives stock first  
**And** repeated allocation jobs do not over-allocate.

## 4. Duplicate Reservation Command

**Given** a completed reservation operation  
**When** the same operation key and payload are submitted again  
**Then** the original result is returned  
**And** no additional movement or history entry is created.

## 5. Idempotency Conflict

**Given** an operation key was used to reserve 5 units  
**When** the same key is reused with quantity 7  
**Then** the request is rejected as a conflict  
**And** inventory remains unchanged.

## 6. Transaction Rollback

**Given** an injected failure after a movement is prepared  
**When** the operation throws before commit  
**Then** balance, movement, reservation, operation, and history writes all roll back  
**And** retrying the same intent can succeed.

## 7. Order Quantity Reduction

**Given** an item has 5 reserved, 2 picked, and 1 shipped  
**When** the ordered quantity is reduced by 4  
**Then** at most 4 reserved units are released  
**And** picked and shipped quantities are not silently changed.

## 8. Pick, Pack, and Reversal

**Given** a confirmed reservation  
**When** quantity is picked and packed  
**Then** it moves through reserved, picked, and packed buckets  
**And** cancellation cannot make packed stock immediately available  
**And** unpack and return actions create explicit compensating movements.

## 9. Available-Stock Transfer

**Given** 10 available and 4 reserved at the source warehouse  
**When** 7 units are transferred  
**Then** 7 available units move to the destination  
**And** the 4 reserved units remain at the source.

## 10. Transfer Exceeds Availability

**Given** 3 available units at the source  
**When** 4 units are requested for transfer  
**Then** the entire transfer is rejected  
**And** neither warehouse balance changes.

## 11. Partial Shipment

**Given** a reservation with 6 packed units<br>
**When** a shipment item is created for 4 and that complete shipment is confirmed<br>
**Then** the shipment item's quantity identifies the 4 units assigned from that reservation<br>
**And** 4 move from packed to external shipped<br>
**And** 2 remain packed<br>
**And** the reservation and order item remain partially fulfilled.

## 12. Provider Timeout

**Given** a packed shipment<br>
**When** the mock provider accepts it but the submission response times out<br>
**Then** the provider submission becomes uncertain<br>
**And** the shipment remains pending handoff<br>
**And** the mock provider retains one external shipment under the stable request key<br>
**And** packed and on-hand balances remain unchanged<br>
**And** retry and status lookup use the same provider request key<br>
**And** only the later signed confirmation callback marks the shipment shipped.

## 13. Permanent Provider Failure

**Given** a packed shipment<br>
**When** the provider permanently rejects it<br>
**Then** the provider submission becomes permanently failed<br>
**And** inventory remains packed<br>
**And** no inventory deduction occurs.

## 14. Duplicate Shipment Callback

**Given** a `shipment.confirmed` webhook has been processed<br>
**When** the provider sends the same webhook again<br>
**Then** the callback is acknowledged<br>
**And** no additional shipment movement or quantity update occurs.

## 15. Out-of-Order Delivery Callback

**Given** the shipment is not confirmed<br>
**When** a delivery-confirmed webhook arrives<br>
**Then** its provider webhook receipt is persisted as pending<br>
**And** inventory is not deducted by the delivery webhook<br>
**And** processing resumes only when prerequisite state exists.

## 16. Reservation Expiration

**Given** an expired temporary reservation with quantity still reserved  
**When** the expiration command runs  
**Then** reserved quantity returns to available  
**And** movement and reservation history are recorded  
**And** repeated expiration processing has no additional effect.

## 17. Worker Retry

**Given** a job fails after its domain operation commits  
**When** the queue retries the job  
**Then** domain idempotency returns the original result  
**And** no inventory effect is repeated.

## 18. HMAC Verification

**Given** a callback with a missing, expired, or invalid signature  
**When** it reaches the webhook endpoint  
**Then** it is rejected before domain processing  
**And** inventory remains unchanged.

## 19. Partial Result in the Operational UI

**Given** an administrator requests 10 units through a Blade form and only 6 are available<br>
**When** the reservation service completes the operation<br>
**Then** the redirected page visibly reports 10 requested, 6 allocated, and 4 outstanding<br>
**And** the result is not labelled as a full reservation.

## 20. Duplicate Browser Submission

**Given** an authenticated business-mutation form contains an operation key and its first submission commits  
**When** the browser submits the same form again  
**Then** the original result is displayed  
**And** no movement, transition, or quantity effect is repeated.

## 21. Core Operation Without JavaScript

**Given** JavaScript is unavailable<br>
**When** an authenticated administrator performs a required operation through its server-rendered form<br>
**Then** validation, authorization, idempotency, and the application service still execute correctly<br>
**And** the resulting state is visible after redirect.

## 22. Inventory Reporting

**Given** stock exists across multiple products, warehouses, and lifecycle stages  
**When** the administrator opens the inventory report  
**Then** available, reserved, picked, packed, on-hand, and shipped totals are filterable and aggregated correctly  
**And** shipped totals come from confirmed packed-to-external movements rather than current warehouse on-hand stock.

## 23. Open Commitments and Consumed Inventory

**Given** the system contains open and closed reservations plus orders with and without confirmed shipment handoff  
**When** the administrator opens the operational reports  
**Then** the open-reservation report includes only reservations with active stage quantity  
**And** the consumed-inventory report includes only orders with quantity moved from packed to external/shipped after confirmed carrier handoff.

## 24. Accepted Submission Is Not Yet Shipped

**Given** the provider accepts a packed shipment<br>
**When** no shipment-confirmed callback has been processed<br>
**Then** the shipment is not marked shipped<br>
**And** inventory remains packed.

## 25. Manual Mock Confirmation

**Given** an accepted mock-provider shipment in the local environment<br>
**When** the administrator requests “Send shipment confirmation now”<br>
**Then** the mock provider persists a webhook and sends it as a signed HTTP callback<br>
**And** the control does not call the shipment-confirmation service directly<br>
**And** inventory moves to shipped only after webhook processing.

## 26. Outbound Transport Retry

**Given** a persisted mock-provider webhook<br>
**When** its HTTP delivery times out or receives a retryable response<br>
**Then** it remains retryable with the same external event ID and raw body<br>
**And** a later successful delivery creates at most one provider webhook receipt and one business effect.

## 27. Reconciliation Does Not Bypass the Webhook

**Given** an uncertain local submission whose provider status is handoff confirmed<br>
**When** reconciliation queries the stable provider request key<br>
**Then** the existing mock-provider confirmation webhook becomes deliverable again<br>
**And** reconciliation itself does not deduct inventory.
