<?php
require_once '../includes/auth.php';
require_once '../includes/student-functions.php';
require_once '../includes/ai-mood-engine.php';

// Ensure user is logged in and is a student
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$message = '';
$message_type = '';

// Check if user has already submitted mood today
$stmt = $pdo->prepare("
    SELECT * FROM mood_entries 
    WHERE user_id = ? AND DATE(created_at) = ?
");
$stmt->execute([$user_id, $today]);
$existing_entry = $stmt->fetch();

// Get today's date for display
$today_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Wellness Check-in - Student Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0a0f;
            background-image: 
                radial-gradient(at 0% 0%, rgba(88, 28, 135, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(17, 24, 39, 0.9) 0px, transparent 50%);
        }
        
        .glass-card {
            background: rgba(18, 18, 24, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(75, 85, 99, 0.2);
            border-radius: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        
        .glass-card:hover {
            background: rgba(24, 24, 32, 0.9);
            border-color: rgba(139, 92, 246, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 12px 48px rgba(0, 0, 0, 0.5);
        }
        
        .mood-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            background: rgba(24, 24, 32, 0.6);
        }
        
        .mood-card.selected {
            border-color: #8b5cf6;
            background: rgba(139, 92, 246, 0.15);
            transform: scale(1.05);
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.2);
        }
        
        .mood-card:hover {
            transform: scale(1.05);
            background: rgba(139, 92, 246, 0.1);
        }
        
        .mood-card i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #8b5cf6;
        }
        
        .progress-bar {
            height: 8px;
            background: rgba(75, 85, 99, 0.2);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #8b5cf6, #3b82f6);
            transition: width 0.5s ease;
            box-shadow: 0 0 10px rgba(139, 92, 246, 0.3);
        }
        
        input[type=range] {
            -webkit-appearance: none;
            height: 8px;
            background: rgba(75, 85, 99, 0.2);
            border-radius: 5px;
        }
        
        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            background: #8b5cf6;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid #fff;
            box-shadow: 0 0 10px rgba(139, 92, 246, 0.3);
        }
        
        input[type=range]::-webkit-slider-thumb:hover {
            transform: scale(1.2);
            background: #a78bfa;
        }
        
        input[type=number], input[type=text], select, textarea {
            background: rgba(24, 24, 32, 0.8) !important;
            border: 1px solid rgba(75, 85, 99, 0.3) !important;
            color: #e5e7eb !important;
            transition: all 0.3s ease;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
        }
        
        input[type=number]:focus, input[type=text]:focus, select:focus, textarea:focus {
            border-color: #8b5cf6 !important;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1) !important;
            outline: none !important;
            background: rgba(32, 32, 40, 0.9) !important;
        }
        
        input[type=checkbox] {
            width: 1.2rem;
            height: 1.2rem;
            accent-color: #8b5cf6;
            border-radius: 0.25rem;
        }
        
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .section-header {
            cursor: pointer;
            user-select: none;
            color: #f3f4f6;
        }
        
        .section-header:hover {
            opacity: 0.9;
        }
        
        .section-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease-out;
        }
        
        .section-content.active {
            max-height: 1000px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6, #3b82f6);
            transition: all 0.3s ease;
            font-weight: 600;
            letter-spacing: 0.025em;
        }
        
        .btn-primary:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
        }
        
        .btn-secondary {
            background: rgba(31, 41, 55, 0.8);
            transition: all 0.3s ease;
            border: 1px solid rgba(75, 85, 99, 0.3);
        }
        
        .btn-secondary:hover {
            background: rgba(55, 65, 81, 0.9);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(17, 17, 22, 0.8);
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #8b5cf6, #3b82f6);
            border-radius: 5px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #9f7aea, #4f8ef7);
        }
        
        .stat-card {
            background: rgba(24, 24, 32, 0.6);
            border: 1px solid rgba(75, 85, 99, 0.2);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            background: rgba(32, 32, 40, 0.8);
            border-color: rgba(139, 92, 246, 0.3);
        }
        
        .required-badge {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
        }
        
        .section-number {
            background: #8b5cf6;
            width: 2rem;
            height: 2rem;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
            margin-right: 0.75rem;
        }
    </style>
</head>
<body class="min-h-screen text-gray-200">
    <div class="flex h-screen">
        <!-- Include Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Header -->
            <?php include 'header.php'; ?>
            
            <!-- Main Content Area -->
            <div class="p-6 md:p-8">
                <?php if ($existing_entry): ?>
                    <!-- Already Submitted Today -->
                    <div class="glass-card p-8 max-w-2xl mx-auto text-center floating">
                        <div class="text-6xl mb-4 text-purple-400">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h1 class="text-3xl font-bold text-white mb-2">Daily Check-in Complete</h1>
                        <p class="text-gray-400 mb-6">You've already submitted your wellness check-in for today.</p>
                        
                        <!-- Today's Summary -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                            <div class="stat-card rounded-xl p-4">
                                <div class="text-2xl text-purple-400 mb-1">
                                    <i class="fas fa-smile"></i>
                                </div>
                                <div class="text-white font-medium">
                                    <?php
                                    $mood_labels = ['happy' => 'Positive', 'neutral' => 'Neutral', 'sad' => 'Low', 'stressed' => 'Stressed', 'motivated' => 'Motivated'];
                                    echo $mood_labels[$existing_entry['mood']] ?? 'Neutral';
                                    ?>
                                </div>
                                <div class="text-gray-500 text-xs uppercase tracking-wider mt-1">Mood</div>
                            </div>
                            <div class="stat-card rounded-xl p-4">
                                <div class="text-2xl text-purple-400 mb-1">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="text-white font-medium"><?= $existing_entry['stress_level'] ?>/5</div>
                                <div class="text-gray-500 text-xs uppercase tracking-wider mt-1">Stress</div>
                            </div>
                            <div class="stat-card rounded-xl p-4">
                                <div class="text-2xl text-purple-400 mb-1">
                                    <i class="fas fa-bolt"></i>
                                </div>
                                <div class="text-white font-medium"><?= $existing_entry['energy_level'] ?>/5</div>
                                <div class="text-gray-500 text-xs uppercase tracking-wider mt-1">Energy</div>
                            </div>
                            <div class="stat-card rounded-xl p-4">
                                <div class="text-2xl text-purple-400 mb-1">
                                    <i class="fas fa-moon"></i>
                                </div>
                                <div class="text-white font-medium"><?= $existing_entry['sleep_hours'] ?? 8 ?>h</div>
                                <div class="text-gray-500 text-xs uppercase tracking-wider mt-1">Sleep</div>
                            </div>
                        </div>
                        
                        <!-- Risk Level -->
                        <?php if (isset($existing_entry['risk_level'])): ?>
                            <div class="mb-6 p-4 rounded-xl <?= $existing_entry['risk_level'] == 'high' ? 'bg-red-900/20 text-red-300 border border-red-900/30' : ($existing_entry['risk_level'] == 'medium' ? 'bg-yellow-900/20 text-yellow-300 border border-yellow-900/30' : 'bg-green-900/20 text-green-300 border border-green-900/30') ?>">
                                <i class="fas fa-shield-alt mr-2"></i>
                                <span class="font-semibold">Wellness Assessment: </span>
                                <?php if ($existing_entry['risk_level'] == 'low'): ?>
                                    <span>Excellent - Maintaining Well-being</span>
                                <?php elseif ($existing_entry['risk_level'] == 'medium'): ?>
                                    <span>Moderate - Monitor Closely</span>
                                <?php else: ?>
                                    <span>High - Support Recommended</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- AI Summary if available -->
                        <?php if (!empty($existing_entry['ai_analysis'])): ?>
                            <div class="mb-6 p-5 bg-purple-900/10 rounded-xl text-gray-300 text-left border border-purple-900/30">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-robot mr-2 text-purple-400"></i>
                                    <span class="font-semibold text-purple-400">AI Analysis</span>
                                </div>
                                <?= htmlspecialchars($existing_entry['ai_analysis']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex justify-center space-x-4">
                            <a href="mood-history.php" class="btn-primary px-6 py-3 rounded-xl inline-flex items-center">
                                <i class="fas fa-chart-line mr-2"></i>
                                View History
                            </a>
                            <a href="dashboard.php" class="btn-secondary px-6 py-3 rounded-xl inline-flex items-center">
                                <i class="fas fa-home mr-2"></i>
                                Dashboard
                            </a>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Daily Wellness Form -->
                    <div class="max-w-3xl mx-auto">
                        <!-- Header -->
                        <div class="text-center mb-8">
                            <h1 class="text-3xl md:text-4xl font-bold text-white mb-3">Daily Wellness Assessment</h1>
                            <p class="text-gray-400 text-lg"><?= $today_date ?></p>
                            <div class="w-24 h-1 bg-gradient-to-r from-purple-500 to-blue-500 mx-auto mt-4 rounded-full"></div>
                        </div>
                        
                        
                        <form action="submit-mood.php" method="POST" id="moodForm">
                            <!-- Section 1: Current State -->
                            <div class="glass-card p-6 mb-4">
                                <div class="section-header flex items-center justify-between" onclick="toggleSection(1)">
                                    <h2 class="text-xl font-semibold text-white flex items-center">
                                        <span class="section-number">1</span>
                                        <span>Current State</span>
                                        <span class="ml-3 required-badge">Required</span>
                                    </h2>
                                    <i class="fas fa-chevron-down text-gray-500 transition-transform" id="icon-1"></i>
                                </div>
                                <div class="section-content active mt-4" id="section-1">
                                    <p class="text-gray-400 mb-4">Select your current emotional state:</p>
                                    
                                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
                                        <?php
                                        $moods = [
                                            'happy' => ['fa-smile', 'Positive', 'text-green-400'],
                                            'neutral' => ['fa-meh', 'Neutral', 'text-gray-400'],
                                            'sad' => ['fa-frown', 'Low', 'text-blue-400'],
                                            'stressed' => ['fa-exclamation-circle', 'Stressed', 'text-red-400'],
                                            'motivated' => ['fa-fire', 'Motivated', 'text-orange-400']
                                        ];
                                        
                                        foreach ($moods as $value => $data):
                                        ?>
                                            <div class="mood-card rounded-xl p-5 text-center text-white flex flex-col items-center" 
                                                 data-mood="<?= $value ?>" 
                                                 onclick="selectMood(this, '<?= $value ?>')">
                                                <i class="fas <?= $data[0] ?> <?= $data[2] ?> mb-2"></i>
                                                <div class="font-medium text-sm"><?= $data[1] ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="mood" id="selectedMood" required>
                                    
                                    <!-- Stress Level -->
                                     <div class="glass-card p-6 mb-4">
                                    <div class="mb-5">
                                        <div class="flex justify-between text-gray-300 mb-2">
                                            <label class="flex items-center">
                                                <i class="fas fa-exclamation-triangle text-purple-400 mr-2"></i>
                                                Stress Level
                                            </label>
                                            <span class="bg-gray-800 px-3 py-1 rounded-full text-purple-400 text-sm" id="stressValue">3/5</span>
                                        </div>
                                        <input type="range" name="stress_level" id="stress" min="1" max="5" value="3" class="w-full" oninput="updateSlider(this, 'stressValue')">
                                        <div class="flex justify-between text-gray-500 text-xs mt-1">
                                            <span>Minimal Stress</span>
                                            <span>High Stress</span>
                                        </div>
                                    </div>
                                    </div>
                                    <!-- Energy Level -->
                                     <div class="glass-card p-6 mb-4">
                                    <div class="mb-4">
                                        <div class="flex justify-between text-gray-300 mb-2">
                                            <label class="flex items-center">
                                                <i class="fas fa-bolt text-purple-400 mr-2"></i>
                                                Energy Level
                                            </label>
                                            <span class="bg-gray-800 px-3 py-1 rounded-full text-purple-400 text-sm" id="energyValue">3/5</span>
                                        </div>
                                        <input type="range" name="energy_level" id="energy" min="1" max="5" value="3" class="w-full" oninput="updateSlider(this, 'energyValue')">
                                        <div class="flex justify-between text-gray-500 text-xs mt-1">
                                            <span>Low Energy</span>
                                            <span>High Energy</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>
                            
                            <!-- Section 2: Academic Activities -->
                            <div class="glass-card p-6 mb-4">
                                <div class="section-header flex items-center justify-between" onclick="toggleSection(2)">
                                    <h2 class="text-xl font-semibold text-white flex items-center">
                                        <span class="section-number">2</span>
                                        <span>Academic Activities</span>
                                    </h2>
                                    <i class="fas fa-chevron-down text-gray-500 transition-transform" id="icon-2"></i>
                                </div>
                                <div class="section-content mt-4" id="section-2">
                                    <div class="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-gray-300 mb-2 flex items-center">
                                                <i class="fas fa-clock text-purple-400 mr-2"></i>
                                                Study Hours
                                            </label>
                                            <input type="number" name="study_hours" min="0" max="24" step="0.5" value="0" class="w-full" onchange="updateProgress()">
                                        </div>
                                        <div class="flex items-center">
                                            <label class="flex items-center text-gray-300">
                                                <input type="checkbox" name="homework_completed" class="mr-3" onchange="updateProgress()">
                                                <i class="fas fa-check-circle text-purple-400 mr-2"></i>
                                                Homework Completed
                                            </label>
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-gray-300 mb-2 flex items-center">
                                                <i class="fas fa-book text-purple-400 mr-2"></i>
                                                Subjects Studied
                                            </label>
                                            <input type="text" name="subjects_studied" class="w-full" placeholder="e.g., Mathematics, Physics, Literature" onchange="updateProgress()">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 3: Media Consumption -->
                            <div class="glass-card p-6 mb-4">
                                <div class="section-header flex items-center justify-between" onclick="toggleSection(3)">
                                    <h2 class="text-xl font-semibold text-white flex items-center">
                                        <span class="section-number">3</span>
                                        <span>Media Consumption</span>
                                    </h2>
                                    <i class="fas fa-chevron-down text-gray-500 transition-transform" id="icon-3"></i>
                                </div>
                                <div class="section-content mt-4" id="section-3">
                                    <div class="mb-4">
                                        <label class="flex items-center text-gray-300">
                                            <input type="checkbox" name="watched_tv" id="watchedTv" class="mr-3" onchange="toggleTvFields(this); updateProgress()">
                                            <i class="fas fa-tv text-purple-400 mr-2"></i>
                                            Watched Television/Streaming
                                        </label>
                                    </div>
                                    
                                    <div id="tvFields" class="hidden space-y-4 mt-4">
                                        <div class="grid md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-gray-300 mb-2">Hours Watched</label>
                                                <input type="number" name="tv_hours" min="0" max="24" step="0.5" class="w-full">
                                            </div>
                                            <div>
                                                <label class="block text-gray-300 mb-2">Program Title</label>
                                                <input type="text" name="tv_show_name" class="w-full" placeholder="Optional">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-gray-300 mb-2">Content Category</label>
                                            <select name="tv_content_type" class="w-full">
                                                <option value="" class="bg-gray-900">Select category</option>
                                                <option value="educational" class="bg-gray-900">Educational</option>
                                                <option value="entertainment" class="bg-gray-900">Entertainment</option>
                                                <option value="news" class="bg-gray-900">News</option>
                                                <option value="documentary" class="bg-gray-900">Documentary</option>
                                                <option value="drama" class="bg-gray-900">Drama</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 4: Digital Device Usage -->
                            <div class="glass-card p-6 mb-4">
                                <div class="section-header flex items-center justify-between" onclick="toggleSection(4)">
                                    <h2 class="text-xl font-semibold text-white flex items-center">
                                        <span class="section-number">4</span>
                                        <span>Digital Device Usage</span>
                                    </h2>
                                    <i class="fas fa-chevron-down text-gray-500 transition-transform" id="icon-4"></i>
                                </div>
                                <div class="section-content mt-4" id="section-4">
                                    <div class="mb-4">
                                        <label class="block text-gray-300 mb-2 flex items-center">
                                            <i class="fas fa-mobile-alt text-purple-400 mr-2"></i>
                                            Mobile Device Usage (hours)
                                        </label>
                                        <input type="number" name="mobile_hours" min="0" max="24" step="0.5" value="0" class="w-full" onchange="updateProgress()">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-300 mb-3 flex items-center">
                                            <i class="fas fa-tasks text-purple-400 mr-2"></i>
                                            Primary Activities
                                        </label>
                                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                            <label class="flex items-center text-gray-400">
                                                <input type="checkbox" name="mobile_purpose[]" value="study" class="mr-2">
                                                <i class="fas fa-graduation-cap mr-2 text-purple-400"></i>
                                                Study
                                            </label>
                                            <label class="flex items-center text-gray-400">
                                                <input type="checkbox" name="mobile_purpose[]" value="social_media" class="mr-2">
                                                <i class="fas fa-users mr-2 text-purple-400"></i>
                                                Social Media
                                            </label>
                                            <label class="flex items-center text-gray-400">
                                                <input type="checkbox" name="mobile_purpose[]" value="gaming" class="mr-2">
                                                <i class="fas fa-gamepad mr-2 text-purple-400"></i>
                                                Gaming
                                            </label>
                                            <label class="flex items-center text-gray-400">
                                                <input type="checkbox" name="mobile_purpose[]" value="entertainment" class="mr-2">
                                                <i class="fas fa-film mr-2 text-purple-400"></i>
                                                Entertainment
                                            </label>
                                            <label class="flex items-center text-gray-400">
                                                <input type="checkbox" name="mobile_purpose[]" value="communication" class="mr-2">
                                                <i class="fas fa-comments mr-2 text-purple-400"></i>
                                                Communication
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 5: Gaming Activity -->
                            <div class="glass-card p-6 mb-4">
                                <div class="section-header flex items-center justify-between" onclick="toggleSection(5)">
                                    <h2 class="text-xl font-semibold text-white flex items-center">
                                        <span class="section-number">5</span>
                                        <span>Gaming Activity</span>
                                    </h2>
                                    <i class="fas fa-chevron-down text-gray-500 transition-transform" id="icon-5"></i>
                                </div>
                                <div class="section-content mt-4" id="section-5">
                                    <div class="mb-4">
                                        <label class="flex items-center text-gray-300">
                                            <input type="checkbox" name="played_games" id="playedGames" class="mr-3" onchange="toggleGameFields(this); updateProgress()">
                                            <i class="fas fa-gamepad text-purple-400 mr-2"></i>
                                            Played Games Today
                                        </label>
                                    </div>
                                    
                                    <div id="gameFields" class="hidden space-y-4 mt-4">
                                        <div class="grid md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-gray-300 mb-2">Game Title</label>
                                                <input type="text" name="game_name" class="w-full" placeholder="Game name">
                                            </div>
                                            <div>
                                                <label class="block text-gray-300 mb-2">Hours Played</label>
                                                <input type="number" name="game_duration" min="0" max="24" step="0.5" class="w-full">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-gray-300 mb-2">Genre</label>
                                            <input type="text" name="game_type" class="w-full" placeholder="e.g., Strategy, RPG, Simulation">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 6: Rest & Recovery -->
                            <div class="glass-card p-6 mb-4">
                                <div class="section-header flex items-center justify-between" onclick="toggleSection(6)">
                                    <h2 class="text-xl font-semibold text-white flex items-center">
                                        <span class="section-number">6</span>
                                        <span>Rest & Recovery</span>
                                    </h2>
                                    <i class="fas fa-chevron-down text-gray-500 transition-transform" id="icon-6"></i>
                                </div>
                                <div class="section-content mt-4" id="section-6">
                                    <div class="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-gray-300 mb-2 flex items-center">
                                                <i class="fas fa-moon text-purple-400 mr-2"></i>
                                                Sleep Duration
                                            </label>
                                            <input type="number" name="sleep_hours" min="0" max="24" step="0.5" value="8" class="w-full" onchange="updateProgress()">
                                        </div>
                                        <div>
                                            <label class="block text-gray-300 mb-2 flex items-center">
                                                <i class="fas fa-star text-purple-400 mr-2"></i>
                                                Sleep Quality
                                            </label>
                                            <select name="sleep_quality" class="w-full">
                                                <option value="good" class="bg-gray-900">Restful</option>
                                                <option value="disturbed" class="bg-gray-900">Interrupted</option>
                                                <option value="poor" class="bg-gray-900">Poor</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 7: Social Interactions -->
                            <div class="glass-card p-6 mb-4">
                                <div class="section-header flex items-center justify-between" onclick="toggleSection(7)">
                                    <h2 class="text-xl font-semibold text-white flex items-center">
                                        <span class="section-number">7</span>
                                        <span>Social Interactions</span>
                                    </h2>
                                    <i class="fas fa-chevron-down text-gray-500 transition-transform" id="icon-7"></i>
                                </div>
                                <div class="section-content mt-4" id="section-7">
                                    <div class="space-y-3">
                                        <label class="flex items-center text-gray-300">
                                            <input type="checkbox" name="talked_family" class="mr-3" onchange="updateProgress()">
                                            <i class="fas fa-home text-purple-400 mr-2"></i>
                                            Family Interaction
                                        </label>
                                        <label class="flex items-center text-gray-300">
                                            <input type="checkbox" name="met_friends" class="mr-3" onchange="updateProgress()">
                                            <i class="fas fa-user-friends text-purple-400 mr-2"></i>
                                            Socialized with Friends
                                        </label>
                                        <label class="flex items-center text-gray-300">
                                            <input type="checkbox" name="felt_lonely" class="mr-3" onchange="updateProgress()">
                                            <i class="fas fa-heart-broken text-purple-400 mr-2"></i>
                                            Experienced Loneliness
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Reflection Notes -->
                            <div class="glass-card p-6 mb-6">
                                <h2 class="text-xl font-semibold text-white mb-4 flex items-center">
                                    <i class="fas fa-pen-fancy text-purple-400 mr-2"></i>
                                    Personal Reflection
                                </h2>
                                <textarea name="notes" rows="4" class="w-full" placeholder="Additional notes about your day, challenges, or achievements..."></textarea>
                                <p class="text-gray-500 text-sm mt-2 flex items-center">
                                    <i class="fas fa-info-circle mr-2 text-purple-400"></i>
                                    Optional - Share any thoughts about your well-being
                                </p>
                            </div>
                            
                            <!-- Submit Button -->
                            <button type="submit" class="btn-primary w-full py-4 rounded-xl text-white text-lg shadow-xl flex items-center justify-center">
                                <i class="fas fa-clipboard-check mr-3"></i>
                                Complete Wellness Assessment
                            </button>
                        </form>
                        
                        <p class="text-center text-gray-500 text-sm mt-4 flex items-center justify-center">
                            <i class="fas fa-shield-alt mr-2 text-purple-400"></i>
                            All responses are confidential and secure
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Mood Selection
        function selectMood(element, mood) {
            document.querySelectorAll('.mood-card').forEach(card => {
                card.classList.remove('selected');
            });
            element.classList.add('selected');
            document.getElementById('selectedMood').value = mood;
            updateProgress();
        }
        
        // Update Slider Values
        function updateSlider(slider, displayId) {
            document.getElementById(displayId).textContent = slider.value + '/5';
            updateProgress();
        }
        
        // Toggle TV Fields
        function toggleTvFields(checkbox) {
            document.getElementById('tvFields').classList.toggle('hidden', !checkbox.checked);
        }
        
        // Toggle Game Fields
        function toggleGameFields(checkbox) {
            document.getElementById('gameFields').classList.toggle('hidden', !checkbox.checked);
        }
        
        // Toggle Sections
        function toggleSection(sectionNum) {
            const content = document.getElementById(`section-${sectionNum}`);
            const icon = document.getElementById(`icon-${sectionNum}`);
            
            content.classList.toggle('active');
            icon.style.transform = content.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0deg)';
        }
        
        // Update Progress Bar
        function updateProgress() {
            let completed = 0;
            let total = 7; // Total sections
            
            // Check mood selected
            if (document.getElementById('selectedMood').value) completed++;
            
            // Check stress and energy (always have values)
            completed += 2;
            
            // Check other sections
            const sections = ['study_hours', 'mobile_hours', 'sleep_hours'];
            sections.forEach(id => {
                const input = document.querySelector(`[name="${id}"]`);
                if (input && input.value && parseFloat(input.value) > 0) completed++;
            });
            
            // Check checkboxes
            const checkboxes = ['homework_completed', 'talked_family', 'met_friends', 'felt_lonely'];
            checkboxes.forEach(name => {
                if (document.querySelector(`[name="${name}"]:checked`)) completed++;
            });
            
            const percentage = Math.min(100, Math.round((completed / total) * 100));
            document.getElementById('progressPercentage').textContent = percentage + '%';
            document.getElementById('progressBar').style.width = percentage + '%';
        }
        
        // Form Validation
        document.getElementById('moodForm').addEventListener('submit', function(e) {
            const mood = document.getElementById('selectedMood').value;
            if (!mood) {
                e.preventDefault();
                alert('Please select your current emotional state to continue.');
            }
        });
        
        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            updateSlider(document.getElementById('stress'), 'stressValue');
            updateSlider(document.getElementById('energy'), 'energyValue');
            
            // Add smooth transitions
            const style = document.createElement('style');
            style.textContent = `
                .mood-card.selected {
                    box-shadow: 0 0 30px rgba(139, 92, 246, 0.15);
                }
                .mood-card i {
                    transition: transform 0.3s ease;
                }
                .mood-card:hover i {
                    transform: scale(1.1);
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>