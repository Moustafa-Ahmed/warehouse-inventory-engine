The Challenge — "Warehouse Inventory Reservation Engine"
The Story
Innov8 provides ERP and Logistics software used by companies operating multiple warehouses.

When customers place sales orders, inventory cannot simply be deducted immediately.

Instead, products move through several business stages:

Available inventory

Reserved inventory

Picked inventory

Packed inventory

Shipped inventory

Delivered inventory

Meanwhile, many things may happen:

Multiple users reserve inventory simultaneously.

Warehouse operators cancel reservations.

Orders are edited after reservation.

Shipments fail.

Orders are partially fulfilled.

Background jobs may retry.

External shipping providers may send duplicate webhooks.

Inventory must never become negative.

The same inventory must never be reserved twice.

Your task is to design the Inventory Reservation Engine, the core component responsible for ensuring inventory always remains correct regardless of retries, duplicate events, or concurrent operations.

Think of this as the heart of an ERP system where inventory correctness is more important than feature count.

What the System Must Do
Build the inventory core responsible for:

Managing stock across warehouses

Reserving inventory safely

Releasing reservations

Confirming shipment

Handling partial shipments

Recording inventory movements

Receiving duplicate shipment callbacks safely

Maintaining inventory consistency even when jobs fail

At any point, the system should answer:

Current available stock

Reserved stock

Picked stock

Shipped stock

Inventory movement history

Which reservations remain open

Which orders have already consumed inventory

Real World Conditions
Your design should safely handle situations such as:

Two users reserve the last remaining item simultaneously.

The reservation command runs twice.

The same background job executes multiple times.

A shipment webhook arrives more than once.

A worker crashes halfway through processing.

Orders are partially cancelled after reservation.

Products are transferred between warehouses while reservations exist.

Inventory updates must remain consistent under concurrent requests.

Some Business Rules Are Intentionally Missing
Real ERP systems rarely have perfectly defined requirements.

For example:

Can reservations expire?

How should partial shipments affect reservations?

Can inventory be transferred while reserved?

Should reservations lock inventory immediately?

How should overselling be prevented?

These decisions are intentionally left open.

Choose sensible rules.

Document your assumptions.

Explain your trade-offs.

We are evaluating your engineering judgment as much as your implementation.

📦 What to Hand In
You do not need to build an entire ERP.

Focus on building the inventory engine correctly.

Required

1. Database Schema + Migrations
   Include tables for:

Products

Warehouses

Inventory

Reservations

Orders

Shipment records

Inventory movements

Reservation history

2. Inventory Reservation Logic
   Implement the business logic responsible for:

Reserving stock

Preventing overselling

Releasing reservations

Partial reservation handling

Reservation expiration (if you choose to support it)

3. Shipment Processing
   Implement an Artisan command and queued jobs that:

Process pending shipments

Confirm inventory deduction

Handle retries safely

Prevent duplicate shipment processing

4. Mock Shipping Provider
   Implement a fake shipping provider that randomly:

Succeeds

Fails permanently

Times out

Sends duplicate delivery confirmations

Delays confirmation before reporting success

Show how your design safely handles every case.
