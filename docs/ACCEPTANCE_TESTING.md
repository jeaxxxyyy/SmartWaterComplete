# Smart Water Acceptance Testing

## Automated coverage completed

- Authenticated device activation and telemetry upload
- Rejection of missing, invalid, suspended, or mismatched device credentials
- Duplicate `reading_id` idempotency
- Month-to-month server consumption rollover
- Pending telemetry persistence and retry acknowledgment contract
- Water rate, fixed charge, subsidy, and zero-floor billing calculation
- Finalized statements, partial offline payments, receipts, and household isolation
- Monthly usage-limit configuration and historical threshold repair
- Login and password rate limiting
- Security headers and expiring web tokens
- Responsive household dashboard and billing presentation

Run the complete automated suite with:

```powershell
php artisan test
npm run build
```

## Manual software acceptance checklist

- [ ] Administrator can create and approve a household account.
- [ ] Administrator can register, assign, activate, suspend, and decommission a meter.
- [ ] Household sees the correct current-month consumption and complete estimated charge.
- [ ] Household sees Offline when the meter has not reported within the configured interval.
- [ ] A queued reading is accepted once after connectivity returns; retrying the same ID does not double count.
- [ ] A new calendar month starts server period consumption at zero.
- [ ] A subsidy cannot reduce the current charge below zero.
- [ ] Administrator can finalize a statement and record a partial or full offline payment.
- [ ] Household can download only its own statement and receipt.
- [ ] Notification, leak, report, and audit-log filters work with empty and populated data.
- [ ] Dashboard tasks are usable at 390px mobile width and desktop width without page-level horizontal scrolling.
- [ ] Keyboard focus is visible and every form control has an accessible name.
- [ ] Database backup can be restored into a separate test database.

## Outstanding physical validation

The measured-water calibration remains pending. Software tests cannot establish the real pulses-per-liter accuracy of the installed sensor. Record this limitation clearly in the capstone presentation until a measured-volume test can be performed.

## Future real-user validation

When participants are available, ask at least three household users to find current consumption, the complete estimated bill, meter status, leak alerts, and consumption history without coaching. Record completion time and confusion points before claiming full usability validation.
