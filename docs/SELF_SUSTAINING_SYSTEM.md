# 🔄 Self-Sustaining 24/7 System

## Overview

Avinash-EYE is now a fully self-sustaining system that runs 24/7 with automatic training, reanalysis, and health monitoring. No manual intervention required!

---

## ✨ Automated Features

### 1. **System Health Monitoring** (Every 5 minutes)
- ✅ Monitors Python AI service
- ✅ Monitors Ollama service
- ✅ Monitors Database connectivity
- ✅ Monitors Queue status
- ✅ Auto-fixes stuck images
- ✅ Alerts on issues

### 2. **Auto-Training** (Daily at 2 AM)
- ✅ Exports training data from uploaded images
- ✅ Triggers AI model training
- ✅ Only trains when enough new images (50+)
- ✅ Improves descriptions over time
- ✅ Runs during low-activity hours

### 3. **Auto-Reanalysis** (Every 6 hours)
- ✅ Gradually reanalyzes all images
- ✅ Applies improved AI models
- ✅ Prioritizes oldest images first
- ✅ Processes 25 images per run
- ✅ Non-disruptive small batches

### 4. **Training Data Export** (Daily at 1 AM)
- ✅ Automatic backup of training data
- ✅ Exports up to 5,000 recent images
- ✅ Ready for AI training
- ✅ Historical data preservation

### 5. **Ollama 24/7 Reliability**
- ✅ Auto-restart on failure
- ✅ Auto-pull required models
- ✅ Health checks every 30 seconds
- ✅ Unlimited restart attempts
- ✅ Never goes offline

### 6. **Queue Worker 24/7**
- ✅ Dedicated background processor
- ✅ Auto-restart on failure
- ✅ Processes 100 jobs per cycle
- ✅ Max 1 hour per cycle
- ✅ Always processing

---

## 📋 Scheduled Tasks

| Task | Schedule | Purpose | Batch Size |
|------|----------|---------|------------|
| **System Monitor** | Every 5 min | Health checks & auto-fix | - |
| **Export Training Data** | Daily 1 AM | Backup training data | 5,000 images |
| **Auto-Train AI** | Daily 2 AM | Improve AI models | All images |
| **Auto-Reanalyze** | Every 6 hours | Update old images | 25 images |
| **Cleanup Failed Jobs** | Weekly Sun 3 AM | Prune old failures | 7 days old |

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Scheduler Container                       │
│                   (Laravel schedule:work)                    │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Every 5 minutes → System Health Monitor            │   │
│  │  Daily 1 AM      → Export Training Data             │   │
│  │  Daily 2 AM      → Auto-Train AI                    │   │
│  │  Every 6 hours   → Auto-Reanalyze Images (25)       │   │
│  │  Weekly Sun 3AM  → Cleanup Old Failed Jobs          │   │
│  └─────────────────────────────────────────────────────┘   │
└────────────────────┬────────────────────────────────────────┘
                     │
         ┌───────────┼───────────┐
         │           │           │
┌────────▼────┐ ┌───▼──────┐ ┌─▼─────────┐
│Queue Worker │ │Python AI │ │  Ollama   │
│   24/7      │ │   24/7   │ │   24/7    │
│Auto-restart │ │Auto-train│ │Auto-pull  │
└─────────────┘ └──────────┘ └───────────┘
```

---

## 🔄 How It Works

### System Monitoring Flow

```
┌─ Every 5 Minutes ────────────────────────────────────────┐
│                                                           │
│ 1. Check Python AI health                                │
│ 2. Check Ollama service                                  │
│ 3. Check Database connection                             │
│ 4. Check Queue status (pending/failed)                   │
│ 5. Detect stuck images (processing > 10 min)             │
│ 6. Auto-fix issues:                                      │
│    - Reset stuck images to pending                       │
│    - Log warnings                                        │
│ 7. Report status                                         │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

### Auto-Training Flow

```
┌─ Daily at 2 AM ──────────────────────────────────────────┐
│                                                           │
│ 1. Count total completed images                          │
│ 2. Check if enough new images since last training (50+)  │
│ 3. Export training data (5,000 images max)               │
│ 4. Trigger Python AI training                            │
│ 5. AI learns from your descriptions:                     │
│    - Category patterns                                   │
│    - Description styles                                  │
│    - Tag relationships                                   │
│    - Face patterns                                       │
│ 6. Record training time                                  │
│ 7. Future images benefit from learned patterns           │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

### Auto-Reanalysis Flow

```
┌─ Every 6 Hours ──────────────────────────────────────────┐
│                                                           │
│ 1. Select 25 oldest images (or failed, or random)        │
│ 2. Dispatch reanalysis jobs to queue                     │
│ 3. Queue worker processes with latest AI models          │
│ 4. Updated descriptions, tags, embeddings                │
│ 5. Better search results over time                       │
│ 6. Gradual improvement of entire collection              │
│                                                           │
│ At 25 images every 6 hours:                              │
│ - 100 images/day improved                                │
│ - 700 images/week improved                               │
│ - 3,000 images/month improved                            │
│ - Entire 10k collection improved in ~3 months            │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

---

## 🎮 Manual Controls

While the system is fully automated, you can still trigger tasks manually:

### Immediate System Check
```bash
docker compose exec laravel-app php artisan system:monitor --fix
```

### Immediate Training
```bash
docker compose exec laravel-app php artisan ai:auto-train --force
```

### Immediate Reanalysis
```bash
# Reanalyze 50 failed images
docker compose exec laravel-app php artisan ai:auto-reanalyze --batch=50 --priority=failed

# Reanalyze 100 random images
docker compose exec laravel-app php artisan ai:auto-reanalyze --batch=100 --priority=random

# Reanalyze 25 oldest images (default)
docker compose exec laravel-app php artisan ai:auto-reanalyze --batch=25 --priority=oldest
```

### View Scheduled Tasks
```bash
docker compose exec laravel-app php artisan schedule:list
```

### Monitor Scheduler
```bash
docker compose logs -f scheduler
```

---

## 📊 Monitoring

### Check Scheduler Status
```bash
# Is scheduler running?
docker compose ps scheduler

# View scheduler logs
docker compose logs --tail=100 scheduler

# Follow scheduler in real-time
docker compose logs -f scheduler
```

### Check System Health
```bash
# View latest health check results
docker compose logs --tail=50 scheduler | grep "System Health"

# Run manual health check
docker compose exec laravel-app php artisan system:monitor
```

### Monitor Training
```bash
# View training logs
docker compose logs python-ai | grep -i training

# Check last training time
docker compose exec laravel-app cat storage/app/training/last_training.txt
```

### Monitor Reanalysis Progress
```bash
# View reanalysis logs
docker compose logs scheduler | grep "Auto-reanalysis"

# Check queue for pending jobs
docker compose exec laravel-app php artisan queue:monitor
```

---

## 🔧 Configuration

### Adjust Schedules

Edit `app/Console/Kernel.php`:

```php
// More frequent monitoring (every 2 minutes)
$schedule->command('system:monitor --fix')
    ->everyTwoMinutes()
    ->withoutOverlapping();

// More aggressive reanalysis (every 3 hours, 50 images)
$schedule->command('ai:auto-reanalyze --batch=50 --priority=oldest')
    ->everyThreeHours()
    ->withoutOverlapping();

// More frequent training (every 12 hours)
$schedule->command('ai:auto-train --min-images=25')
    ->everyTwelveHours()
    ->withoutOverlapping();
```

### Adjust Training Threshold

```bash
# Train with fewer new images (25 instead of 50)
docker compose exec laravel-app php artisan ai:auto-train --min-images=25
```

### Adjust Reanalysis Batch Size

```bash
# Larger batches (faster improvement, more resources)
docker compose exec laravel-app php artisan ai:auto-reanalyze --batch=100

# Smaller batches (less resource intensive)
docker compose exec laravel-app php artisan ai:auto-reanalyze --batch=10
```

---

## 🛡️ Reliability Features

### 1. **Ollama Never Offline**
```yaml
restart: always                    # Always restart
restart_policy:
  max_attempts: 0                 # Unlimited attempts
healthcheck:
  retries: 5                      # More retries
  interval: 30s                   # Frequent checks
command: while true; do           # Auto-recovery loop
```

### 2. **Queue Worker Always Processing**
```yaml
restart: always                    # Always restart
command: queue:work               # Long-running process
healthcheck:                      # Process monitoring
max-time: 3600                    # Restart after 1 hour
```

### 3. **Scheduler Always Running**
```yaml
restart: always                    # Always restart
command: schedule:work            # Laravel scheduler
healthcheck:                      # Process monitoring
```

### 4. **Auto-Recovery on Failures**
- Stuck images reset to pending
- Failed jobs retried automatically
- Services restart on crash
- Health checks detect issues
- Alerts logged for review

---

## 📈 Benefits

### Continuous Improvement
- ✅ **AI gets smarter**: Learns from your images
- ✅ **Descriptions improve**: Better captions over time
- ✅ **Search improves**: More accurate results
- ✅ **Old images updated**: Entire collection improves

### Zero Maintenance
- ✅ **No manual intervention**: Everything automated
- ✅ **Self-healing**: Auto-recovery from failures
- ✅ **Always available**: 24/7 operation
- ✅ **Gradual updates**: Non-disruptive improvements

### Resource Efficient
- ✅ **Smart scheduling**: Tasks run during quiet hours
- ✅ **Small batches**: Non-disruptive reanalysis
- ✅ **Conditional training**: Only when beneficial
- ✅ **Background processing**: Doesn't affect user experience

---

## 🧪 Testing the Scheduler

### 1. Verify Scheduler is Running
```bash
docker compose ps scheduler
# Should show "Up" and "healthy"
```

### 2. Check Scheduled Tasks
```bash
docker compose exec scheduler php artisan schedule:list
# Shows all scheduled commands and next run time
```

### 3. Run Tasks Manually
```bash
# Test system monitor
docker compose exec scheduler php artisan system:monitor

# Test auto-train
docker compose exec scheduler php artisan ai:auto-train --force

# Test auto-reanalyze
docker compose exec scheduler php artisan ai:auto-reanalyze --batch=5
```

### 4. Monitor Execution
```bash
# Follow scheduler logs
docker compose logs -f scheduler

# Watch for scheduled task execution
docker compose logs scheduler | grep "Running scheduled command"
```

---

## 🎯 Expected Behavior

### First Week
- **Day 1**: System starts, initial training with existing images
- **Day 2**: First auto-training (if 50+ new images), 100 images reanalyzed
- **Day 7**: 700 images reanalyzed, 7 training sessions

### First Month
- **3,000 images** reanalyzed with improved models
- **30 training sessions** (if enough new uploads)
- **Continuous monitoring** (8,640 health checks)
- **System self-healed** multiple times

### Long Term
- **Entire collection** gradually improved
- **AI continuously learning** from your patterns
- **Zero maintenance required**
- **Always available** for uploads and searches

---

## 🆘 Troubleshooting

### Scheduler Not Running
```bash
# Check if container is up
docker compose ps scheduler

# Check logs for errors
docker compose logs scheduler

# Restart scheduler
docker compose restart scheduler
```

### Tasks Not Executing
```bash
# Verify schedule list
docker compose exec scheduler php artisan schedule:list

# Check Laravel logs
docker compose exec scheduler tail storage/logs/laravel.log

# Run task manually to test
docker compose exec scheduler php artisan ai:auto-train --force
```

### Ollama Goes Offline
```bash
# Should never happen with auto-restart, but if it does:
docker compose restart ollama

# Check Ollama logs
docker compose logs ollama

# Verify health
curl http://localhost:11434/api/tags
```

### Too Many Resources Used
```bash
# Reduce reanalysis frequency in Kernel.php
# Change everySixHours() to daily()

# Reduce batch sizes
# Change --batch=25 to --batch=10

# Restart scheduler to apply changes
docker compose restart scheduler
```

---

## 📝 Summary

Your Avinash-EYE system is now **fully self-sustaining**:

✅ **24/7 Operation**: All services always running  
✅ **Auto-Training**: AI learns from your images daily  
✅ **Auto-Reanalysis**: Gradual improvement of all images  
✅ **Auto-Monitoring**: Health checks every 5 minutes  
✅ **Auto-Recovery**: Self-healing on failures  
✅ **Ollama Reliability**: Never goes offline  
✅ **Zero Maintenance**: No manual intervention needed  

**Just upload images and the system does the rest!** 🎉

---

## 🚀 Quick Reference

| Want to... | Command |
|------------|---------|
| Check system health | `docker compose exec laravel-app php artisan system:monitor` |
| Force training | `docker compose exec laravel-app php artisan ai:auto-train --force` |
| Reanalyze images | `docker compose exec laravel-app php artisan ai:auto-reanalyze --batch=50` |
| View schedules | `docker compose exec scheduler php artisan schedule:list` |
| Monitor scheduler | `docker compose logs -f scheduler` |
| Check Ollama | `curl http://localhost:11434/api/tags` |
| Restart everything | `docker compose restart` |

---

**Your system is now self-sustaining! Set it and forget it!** 🎊

