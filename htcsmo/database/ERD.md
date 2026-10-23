# Entity-relationship diagram

This ERD is reverse-engineered from the Laravel migrations and Eloquent models in this repository. It describes the current application domain schema, not a live database introspection. If the Aiven database has drifted from the migrations, generate a fresh diagram from that database to capture those differences.

```mermaid
erDiagram
    DEPARTMENTS {
        bigint id PK
        string dept_code UK
        string dept_name
        text description
        timestamp created_at
        timestamp updated_at
    }

    USERS {
        bigint id PK
        bigint department_id FK
        string name
        string username UK
        string school_id UK
        string role
        string account_status
        unsigned_integer account_version
        string email UK
        string profile_photo
        timestamp email_verified_at
        string password
        timestamp created_at
        timestamp updated_at
    }

    REQUISITIONS {
        bigint id PK
        bigint user_id FK
        string tracking_number UK
        string request_type
        string status
        timestamp stage_started_at
        timestamp closed_at
        text purpose
        text remarks
        decimal grand_total
        timestamp submitted_at
        timestamp sla_warning_at
        timestamp due_at
        timestamp created_at
        timestamp updated_at
    }

    REQUISITION_ITEMS {
        bigint id PK
        bigint requisition_id FK
        bigint supply_id FK
        string item_name
        text specifications
        integer quantity
        string unit
        decimal unit_price
        decimal subtotal
        timestamp created_at
        timestamp updated_at
    }

    SUPPLIES {
        bigint id PK
        bigint supply_category_id FK
        bigint supply_unit_id FK
        string item_name UK
        string model_number
        string requisition_tier
        text specifications
        integer quantity
        decimal unit_price
        integer min_stock_level
        timestamp deleted_at
        timestamp created_at
        timestamp updated_at
    }

    SUPPLY_CATEGORIES {
        bigint id PK
        string category_name UK
        text description
        timestamp created_at
        timestamp updated_at
    }

    SUPPLY_UNITS {
        bigint id PK
        string unit_name UK
        string abbreviation
        timestamp created_at
        timestamp updated_at
    }

    SUPPLY_OPTIONS {
        bigint id PK
        bigint supply_id FK
        string name
        unsigned_small_integer sort_order
        timestamp created_at
        timestamp updated_at
    }

    SUPPLY_OPTION_VALUES {
        bigint id PK
        bigint supply_option_id FK
        string value
        unsigned_small_integer sort_order
        timestamp created_at
        timestamp updated_at
    }

    APPROVAL_LOGS {
        bigint id PK
        bigint requisition_id FK
        bigint acted_by_user_id FK
        string action
        string role
        text remarks
        timestamp created_at
        timestamp updated_at
    }

    DIGITAL_SIGNATURES {
        bigint id PK
        bigint approval_log_id FK_UK
        string signature_token UK
        string signer_name
        string signer_role
        string ip_address
        text user_agent
        timestamp signed_at
        timestamp created_at
        timestamp updated_at
    }

    SUPPLY_INVENTORY_LOGS {
        bigint id PK
        bigint supply_id FK
        bigint user_id FK
        bigint requisition_id FK
        string transaction_type
        integer quantity_change
        integer balance_after
        text remarks
        timestamp created_at
        timestamp updated_at
    }

    NOTIFICATIONS {
        bigint id PK
        bigint user_id FK
        string title
        text message
        string icon
        string type
        string event_type
        string action_type
        unsigned_bigint action_id
        string dedupe_key
        boolean is_read
        timestamp read_at
        timestamp created_at
        timestamp updated_at
    }

    USER_ACTIVITY_LOGS {
        bigint id PK
        bigint user_id FK
        string action
        string ip_address
        text user_agent
        timestamp created_at
    }

    EMAIL_LOGS {
        bigint id PK
        bigint user_id FK
        string recipient_email
        string subject
        string status
        text error_message
        timestamp sent_at
    }

    INSTITUTIONAL_HOLIDAYS {
        bigint id PK
        date holiday_date UK
        string name
        timestamp created_at
        timestamp updated_at
    }

    DEPARTMENTS ||--o{ USERS : groups
    USERS ||--o{ REQUISITIONS : submits
    REQUISITIONS ||--o{ REQUISITION_ITEMS : contains
    SUPPLIES o|--o{ REQUISITION_ITEMS : fulfills
    SUPPLY_CATEGORIES o|--o{ SUPPLIES : classifies
    SUPPLY_UNITS o|--o{ SUPPLIES : measures
    SUPPLIES ||--o{ SUPPLY_OPTIONS : offers
    SUPPLY_OPTIONS ||--|{ SUPPLY_OPTION_VALUES : contains
    REQUISITIONS ||--o{ APPROVAL_LOGS : records
    USERS o|--o{ APPROVAL_LOGS : acts_in
    APPROVAL_LOGS ||--o| DIGITAL_SIGNATURES : may_have
    SUPPLIES ||--o{ SUPPLY_INVENTORY_LOGS : records
    USERS ||--o{ SUPPLY_INVENTORY_LOGS : performs
    REQUISITIONS o|--o{ SUPPLY_INVENTORY_LOGS : relates_to
    USERS ||--o{ NOTIFICATIONS : receives
    USERS ||--o{ USER_ACTIVITY_LOGS : generates
    USERS o|--o{ EMAIL_LOGS : associated_with
```

## Relationship and schema notes

- A requisition belongs to one requesting user and contains one or more requisition items. Its item rows retain a unit-price snapshot; `supply_id` is nullable so a historical request item can remain if its catalog supply is deleted.
- Catalog units and selectable attributes are normalized through `supply_units`, `supply_options`, and `supply_option_values`. Requisition item names, specifications, units, and prices remain intentional historical snapshots.
- `users.account_status` is the persisted account state. The model's `is_approved` property is a compatibility value derived from whether that status is `active`.
- Deleting a department or supply category leaves its user or supply row in place and clears the nullable foreign key.
- Approval logs belong to requisitions. A digital signature is optional for an approval log, with at most one signature per log.
- New approval logs identify their acting user through nullable `acted_by_user_id`; it remains nullable so imported historical audit entries can be retained.
- Inventory transactions reference a supply and responsible user; the requisition reference is optional and is cleared if that requisition is deleted.
- Supplies use soft deletion so catalog retirement does not erase inventory-ledger history. Database checks reject negative stock, prices, thresholds, totals, and ledger balances.
- `NOTIFICATIONS.action_type` and `action_id` form an application-level action reference, not a foreign key. They can point to different action types, so the database does not enforce a target table.
- Requisition status and tier remain controlled application strings. Separate lookup tables are unnecessary unless they gain editable metadata; storing both text values and lookup IDs would create duplicate sources of truth.
- `institutional_holidays` is a standalone date lookup used by the submission SLA logic.
- Laravel/framework tables and the removed legacy `supply_requests` / `request_items` tables are omitted from the diagram.
