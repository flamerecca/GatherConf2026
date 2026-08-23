---
paths:
  - 'app/Models/**'
---

# Models

## Eloquent mass-assignment / hidden 設定用 Attribute class
用 PHP Attribute（`#[Fillable([...])]`、`#[Hidden([...])]`）設定 Eloquent 的 mass assignment 與隱藏欄位，不要用 `protected $fillable` / `protected $hidden` 屬性寫法。
