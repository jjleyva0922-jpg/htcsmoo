This script supplies synthetic data for demonstrating the inventory forecast to the dean. It creates six supplies named `[DEMO FORECAST] ...`, with 24 months of releases per item. Dates follow the month when you run it, using Philippine time.

1. Run the current Laravel migrations on your presentation database. This script uses the normalized category and unit tables introduced on October 19, 2026.
2. In MySQL Workbench or phpMyAdmin, select the database used by your presentation app. Have an approved, active SMO or admin account. The script attributes the simulated logs to that account and labels their remarks as synthetic.
3. Open `database/sample_forecasting.sql` and execute the entire script. The script temporarily disables `SQL_SAFE_UPDATES` for the two demo reset statements and immediately restores the connection's previous setting. No Workbench preference change or reconnect is needed. In Workbench, enable stopping on errors; if an error occurs, run `ROLLBACK;` and `SET SESSION SQL_SAFE_UPDATES = 1;` before retrying, since rollback does not restore session settings. No catalog import is required.
4. Verify the final result: six supplies, each with **24 months of history and 24 release records**. If the preflight says no active account exists, it skips all persistent changes.
5. From the project folder, run `php artisan cache:clear`, refresh Inventory, and open Forecast. Direct SQL imports bypass the application's cache invalidation, so an old forecast may otherwise remain for five minutes.
6. Select Monthly or Quarterly and use the forecast download button for the PDF presentation.

The examples demonstrate these behaviors:

| Demo item | Presentation point |
| --- | --- |
| Bond Paper A4 | Higher simulated usage in March, October, and November; June also increases. |
| Whiteboard Marker | Zero stock triggers urgency; June and November have seasonal peaks. |
| Ballpoint Pen | Stable demand with a current stock shortage. |
| Alcohol 500mL | Increased consumption over the most recent six months. |
| Liquid Hand Soap | Current stock covers monthly demand but leaves a safety stock gap. |
| Brown Envelope | Stock covers both monthly and quarterly demand; no purchase recommendation is expected. |

On October 23, 2026, the monthly forecast targets **November 2026** and the quarterly forecast covers **November 2026 through January 2027**. All six items should have high history confidence. That label reflects the amount of simulated history; it does not establish real-world forecast accuracy. Expected results for these six demo supplies alone:

| Demo item | Monthly expected use | Monthly suggested purchase | Quarterly expected use | Quarterly suggested purchase |
| --- | ---: | ---: | ---: | ---: |
| Bond Paper A4 | 86 | 100 | 180 | 217 |
| Whiteboard Marker | 58 | 73 | 118 | 148 |
| Ballpoint Pen | 60 | 50 | 180 | 195 |
| Alcohol 500mL | 33 | 32 | 99 | 114 |
| Liquid Hand Soap | 32 | 7 | 96 | 85 |
| Brown Envelope | 50 | 0 | 150 | 0 |

The demo purchase budgets are **PHP 37,230.00 monthly** and **PHP 91,570.00 quarterly**. Existing inventory and release history can add other recommendations and change the page's overall budget. The page displays up to eight recommendation cards; the downloadable report includes all recommendations.

The forecast combines 65% of historical usage for the target calendar month with 35% of the most recent three-month average. It adds safety stock and subtracts current inventory. Quarterly demand sums the next three monthly forecasts before applying safety stock.

You can rerun the script to restore its six demo stock quantities and replace its tagged history. Existing operational supplies are preserved. Use these reserved demo items only for the presentation, since rerunning resets their quantities. The script adds matched restocks so historical balances reconcile, and creates no requisitions, approvals, notifications, or emails. Demo supplies appear in the inventory/catalog and their logs appear in inventory reports, so use a presentation database and identify the figures as simulated during the presentation.
