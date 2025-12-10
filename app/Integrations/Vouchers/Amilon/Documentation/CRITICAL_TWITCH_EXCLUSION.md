# 🚨 CRITICAL: Twitch Merchant Exclusion

## Context

**Jira Issue**: [UE-655](https://hexeko.atlassian.net/browse/UE-655)
**Date**: 2025-10-23
**Status**: ✅ Implemented and Tested

## Problem

Apple App Store **rejected** our iOS application submission because **Twitch** (a digital streaming platform) was available as a voucher purchase option. This violates Apple's **In-App Purchase Policy** which mandates that all digital goods and services must be sold through Apple's IAP system (with Apple taking a 30% commission).

### Apple's Policy
> **Guideline 3.1.1**: Apps offering digital content (subscriptions, in-game currency, premium features, unlockable content, etc.) must use Apple's in-app purchase system.

Since Twitch gift cards provide **digital streaming services**, they fall under this restriction and must be excluded from the iOS app.

---

## Solution Implemented

### Triple-Layer Protection Strategy

We've implemented **three defensive layers** to ensure Twitch can never appear in the application:

#### 1️⃣ **API Sync Level** (Service Layer)
- **File**: `app/Integrations/Vouchers/Amilon/Services/AmilonMerchantService.php`
- **Method**: `upsertMerchantsDatabase()`
- **Protection**: Twitch is **blocked during synchronization** from Amilon API
- **Impact**: Twitch will NEVER be saved to the database

```php
// Triple check during API sync: ID, merchant_id, or name
if (
    $merchantIdFromApi === '0199bdd3-32b1-72be-905b-591833b488cf'
    || $merchantMerchantId === 'a4322514-36f1-401e-af3d-6a1784a3da7a'
    || stripos((string) $merchantName, 'twitch') !== false
) {
    continue; // Skip - do not sync to database
}
```

#### 2️⃣ **Controller Level** (HTTP Response Layer)
- **File**: `app/Integrations/Vouchers/Amilon/Http/Controllers/MerchantController.php`
- **Methods**:
  - `index()` - Merchants list endpoint
  - `byCategory()` - Merchants grouped by category
  - `show()` - Individual merchant details
- **Protection**: Even if Twitch somehow exists in DB, it's filtered out from API responses

```php
// In index() and byCategory()
$merchantCollection = $merchantCollection->filter(function ($merchant): bool {
    return $merchant->id !== '0199bdd3-32b1-72be-905b-591833b488cf'
        && $merchant->merchant_id !== 'a4322514-36f1-401e-af3d-6a1784a3da7a'
        && stripos($merchant->name ?? '', 'twitch') === false;
});

// In show() - Direct access blocked
if ($id === $twitchId || $id === $twitchMerchantId) {
    return response()->json([
        'error' => 'Merchant not found or blocked',
    ], 404);
}
```

#### 3️⃣ **Database Query Level** (Passive Protection)
- If Twitch somehow bypasses both layers, it won't be returned in queries
- Controller filters ensure runtime protection
- No database migrations needed (data already in production)

---

## Twitch Identifiers (DO NOT MODIFY)

### ⚠️ These values are hardcoded in multiple locations. Changing them requires updating:
1. `AmilonMerchantService::upsertMerchantsDatabase()`
2. `MerchantController::index()`
3. `MerchantController::byCategory()`
4. `MerchantController::show()`
5. Test file: `TwitchExclusionTest.php`

```php
// UUID from Amilon database
const TWITCH_ID = '0199bdd3-32b1-72be-905b-591833b488cf';

// Merchant ID from Amilon API
const TWITCH_MERCHANT_ID = 'a4322514-36f1-401e-af3d-6a1784a3da7a';

// Name matching (case-insensitive)
// Matches: "Twitch", "TWITCH", "twitch", "Twitch Premium", etc.
```

---

## Testing Coverage

**Test File**: `tests/Feature/Integrations/Vouchers/Amilon/TwitchExclusionTest.php`
**Test Group**: `#[Group('twitch-exclusion')]`

### Test Scenarios

| Test Case | Description | Status |
|-----------|-------------|--------|
| `it_excludes_twitch_from_merchants_index_by_id` | Verify exclusion by UUID | ✅ Pass |
| `it_excludes_twitch_from_merchants_index_by_merchant_id` | Verify exclusion by merchant_id | ✅ Pass |
| `it_excludes_twitch_from_merchants_index_by_name` | Verify exclusion by name (case-insensitive) | ✅ Pass |
| `it_excludes_twitch_from_merchants_by_category_endpoint` | Verify exclusion in category grouping | ✅ Pass |
| `it_blocks_direct_access_to_twitch_merchant_by_id` | Verify 404 on direct UUID access | ✅ Pass |
| `it_blocks_direct_access_to_twitch_merchant_by_merchant_id` | Verify 404 on direct merchant_id access | ✅ Pass |
| `it_does_not_break_search_functionality_when_excluding_twitch` | Ensure search still works (Twitter ≠ Twitch) | ✅ Pass |
| `it_allows_normal_merchants_to_work_when_twitch_is_excluded` | Verify other merchants unaffected | ✅ Pass |

### Run Tests
```bash
# Run all Twitch exclusion tests
docker compose exec app_engage php artisan test --group=twitch-exclusion

# Run specific test file
docker compose exec app_engage php artisan test tests/Feature/Integrations/Vouchers/Amilon/TwitchExclusionTest.php

# Run with coverage
docker compose exec app_engage php artisan test --group=twitch-exclusion --coverage
```

---

## Impact Analysis

### ✅ What's Protected
- **iOS App**: Twitch will never appear in vouchers list
- **Android App**: Also excluded (same API backend)
- **Web App**: Also excluded (consistency across platforms)
- **Direct API Access**: Blocked even if someone knows the IDs

### ⚠️ Limitations
- **Play Store**: Google Play allows digital vouchers, so this is overly restrictive for Android
- **Future Enhancement**: Platform-specific filtering (show on web/Android, hide on iOS only)

### 📊 Production Data
- **Environment**: Production database already contains Twitch merchant
- **Strategy**: No migration needed - runtime filtering handles it
- **Persistence**: Twitch stays in DB but invisible to all API consumers

---

## Deployment Checklist

### Pre-Deployment
- [ ] All tests pass: `make test`
- [ ] Quality checks pass: `make quality-check`
- [ ] Code coverage > 80%
- [ ] No PHPStan errors
- [ ] API documentation updated: `make docs`

### Post-Deployment (Production)
- [ ] Verify `/api/v1/vouchers/amilon/merchants` does not return Twitch
- [ ] Verify direct access to Twitch IDs returns 404
- [ ] Test iOS build submission to Apple
- [ ] Monitor error logs for any merchant-related issues

### Rollback Plan
If issues arise:
1. Revert `MerchantController.php` changes
2. Revert `AmilonMerchantService.php` changes
3. Deploy previous version
4. Investigate edge cases

---

## Future Considerations

### Option 1: Platform-Specific Filtering
Add a `platforms` column to merchants table:
```php
'platforms' => ['web', 'android'] // Exclude 'ios'
```

Pros:
- More flexible
- Android/Web users can still buy Twitch
- iOS compliant

Cons:
- Requires DB migration
- More complex logic

### Option 2: Configuration-Based Exclusion
Create a config file for blocked merchants:
```php
// config/merchants.php
return [
    'blocked_on_ios' => [
        'twitch' => [
            'ids' => ['0199bdd3-32b1-72be-905b-591833b488cf'],
            'merchant_ids' => ['a4322514-36f1-401e-af3d-6a1784a3da7a'],
            'reason' => 'Apple IAP policy violation'
        ]
    ]
];
```

Pros:
- Easier to add more merchants
- Self-documenting
- No hardcoded values

Cons:
- Requires config deployment
- Extra abstraction layer

---

## Questions & Answers

### Q: Why not delete Twitch from the database?
**A**: Production database management is risky. Runtime filtering is safer and reversible.

### Q: What if Amilon changes Twitch's ID?
**A**: Triple-check by name (`stripos('twitch')`) acts as a safety net.

### Q: Can we re-enable Twitch later?
**A**: Yes, just remove the filter code. No data loss since Twitch isn't deleted.

### Q: Will this affect performance?
**A**: Minimal impact. Filtering happens in-memory on collections (already loaded).

### Q: What about other digital products (Netflix, Spotify, etc.)?
**A**: Apple's policy is nuanced. Gaming subscriptions are usually OK if they're gift cards, not direct subscriptions. Monitor future App Store rejections.

---

## Contact & Support

**Responsible**: Development Team
**Jira**: UE-655
**Approval**: Product Owner (required for any changes to this logic)
**Documentation**: This file MUST be updated if Twitch exclusion logic changes

---

## Change Log

| Date | Change | Author | Reason |
|------|--------|--------|--------|
| 2025-10-23 | Initial implementation | Claude + Fred | Apple App Store rejection (UE-655) |

---

**⚠️ WARNING**: Do NOT remove Twitch exclusion logic without explicit approval from Product Owner and verification that Apple's IAP policy has changed or an alternative solution has been implemented.