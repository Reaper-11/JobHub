# JobHub Recommendations

## Context-Based Recommendations
This project includes a lightweight, rule-based recommendation engine for job seekers. It scores active jobs using profile preferences and recent behavior, then returns the top results.

### Signals Used
- Profile: preferred category, location, job type, skills
- Behavior: search logs, view logs, applications
- Recency: newer jobs score higher; recent activity weighs more

### How It Works
The scoring logic lives in `includes/recommendation.php` and uses configurable weights. Jobs already applied to are excluded. If there is no user activity, the engine falls back to trending (recently viewed) or newest jobs.

### Tuning Weights
Edit the `$RECOMMENDATION_CONFIG` array in `includes/recommendation.php` to adjust weights, time windows, and limits.
