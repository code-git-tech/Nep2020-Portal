<?php
/**
 * AI Mood Engine
 * Core emotional & behavioral intelligence processor
 * 
 * This engine analyzes mood entries, detects patterns, calculates risk scores,
 * and generates personalized insights and suggestions for students.
 * 
 * @version 2.0
 * @author Your Name
 */

// Prevent direct access
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

class AIMoodEngine {
    private $conn;
    private $userId;
    
    // Mood value mapping for scoring
    private $moodValues = [
        'happy' => 5,
        'motivated' => 5,
        'neutral' => 3,
        'sad' => 2,
        'stressed' => 2
    ];
    
    // Mood emoji mapping for display
    private $moodEmojis = [
        'happy' => '😊',
        'neutral' => '😐',
        'sad' => '😔',
        'stressed' => '😰',
        'motivated' => '🔥'
    ];
    
    // Suggestion library categorized by pattern type
    private $suggestionLibrary = [
        // Sleep-related suggestions
        'sleep' => [
            'critical' => [
                'title' => 'Critical Sleep Deprivation',
                'suggestions' => [
                    '⚠️ Your sleep is dangerously low. Please prioritize 7-9 hours of sleep tonight - your health comes first.',
                    '⚠️ Severe sleep deprivation affects mood, focus, and health. Make sleep your #1 priority tonight.',
                    '⚠️ Consider delaying non-urgent tasks to catch up on sleep. Your brain needs rest to function.'
                ]
            ],
            'insufficient' => [
                'title' => 'Insufficient Sleep',
                'suggestions' => [
                    '😴 Aim for 7-9 hours of sleep - your body needs recovery time. Try going to bed 30 minutes earlier.',
                    '😴 Create a relaxing bedtime routine: dim lights, avoid screens, read a book.',
                    '😴 Consistent sleep schedule helps. Try waking up at the same time even on weekends.',
                    '😴 Avoid caffeine after 4 PM for better sleep quality.'
                ]
            ],
            'poor_quality' => [
                'title' => 'Poor Sleep Quality',
                'suggestions' => [
                    '🌙 Improve sleep quality: keep bedroom dark, cool, and quiet.',
                    '🌙 Avoid screens 1 hour before bed - blue light disrupts sleep hormones.',
                    '🌙 Try meditation or deep breathing before sleep to calm your mind.',
                    '🌙 If you wake up at night, try progressive muscle relaxation.'
                ]
            ],
            'disturbed' => [
                'title' => 'Disturbed Sleep',
                'suggestions' => [
                    '🔄 Avoid heavy meals and excess fluids close to bedtime.',
                    '🔄 Write down worrying thoughts in a notebook before bed.',
                    '🔄 Use white noise or calming sounds to block disruptions.',
                    '🔄 If you can\'t sleep after 20 minutes, get up and do something relaxing.'
                ]
            ],
            'excessive' => [
                'title' => 'Excessive Sleep',
                'suggestions' => [
                    '⏰ Try waking up at the same time each day to regulate your cycle.',
                    '⏰ Limit naps to 20-30 minutes during the day.',
                    '⏰ Get morning sunlight to reset your circadian rhythm.',
                    '⏰ If sleeping too much, you might need more daytime activity.'
                ]
            ],
            'good' => [
                'title' => 'Good Sleep!',
                'suggestions' => [
                    '🌟 Excellent sleep! This sets you up for a productive day.',
                    '🌟 Great rest! Your brain will thank you for the recovery time.',
                    '🌟 Keep up this healthy sleep habit - it\'s the foundation of well-being.'
                ]
            ]
        ],
        
        // Stress-related suggestions
        'stress' => [
            'critical' => [
                'title' => 'Critical Stress Level',
                'suggestions' => [
                    '😰 Your stress is extremely high. Stop and take 5 deep breaths right now.',
                    '😰 Please reach out to a counselor, teacher, or trusted adult. You don\'t have to handle this alone.',
                    '😰 Take a 10-minute break. Step away, stretch, breathe. Your well-being comes first.'
                ]
            ],
            'high' => [
                'title' => 'High Stress',
                'suggestions' => [
                    '🧘 Try the 4-7-8 breathing technique: inhale 4 sec, hold 7 sec, exhale 8 sec.',
                    '🧘 Write down what\'s stressing you - it helps clarify thoughts and reduce overwhelm.',
                    '🧘 Break big problems into smaller, manageable steps. Focus on one thing at a time.',
                    '🧘 Take a short walk outside. Fresh air and movement reduce stress hormones.'
                ]
            ],
            'moderate' => [
                'title' => 'Moderate Stress',
                'suggestions' => [
                    '🌿 Practice mindfulness for 5 minutes. Focus on your breath or surroundings.',
                    '🌿 Listen to calming music or nature sounds during study breaks.',
                    '🌿 Talk to someone about what\'s on your mind - sharing reduces burden.',
                    '🌿 Stretch for 5 minutes to release physical tension.'
                ]
            ],
            'low' => [
                'title' => 'Low Stress',
                'suggestions' => [
                    '😌 You\'re managing stress well! Keep using your coping strategies.',
                    '😌 Maintain this balance with regular breaks and self-care.',
                    '😌 Share your stress management tips with friends who might need them.'
                ]
            ]
        ],
        
        // Energy-related suggestions
        'energy' => [
            'very_low' => [
                'title' => 'Very Low Energy',
                'suggestions' => [
                    '⚡ Drink a glass of water - dehydration often causes fatigue.',
                    '⚡ Get some sunlight exposure for 10 minutes.',
                    '⚡ Have a healthy snack with protein and complex carbs.',
                    '⚡ Take a short power nap (15-20 minutes) if possible.',
                    '⚡ Light stretching or a quick walk can boost energy.'
                ]
            ],
            'low' => [
                'title' => 'Low Energy',
                'suggestions' => [
                    '🔋 Move your body for 10 minutes - walk, stretch, or dance.',
                    '🔋 Eat energy-boosting foods: fruits, nuts, yogurt.',
                    '🔋 Take short breaks every 25 minutes (Pomodoro technique).',
                    '🔋 Check your sleep - low energy often means poor sleep.'
                ]
            ],
            'moderate' => [
                'title' => 'Moderate Energy',
                'suggestions' => [
                    '⚡ Good energy level for focused work. Use it wisely!',
                    '⚡ Alternate between challenging and easier tasks.',
                    '⚡ Stay hydrated to maintain this energy level.',
                    '⚡ Take brief movement breaks to sustain energy.'
                ]
            ],
            'high' => [
                'title' => 'High Energy',
                'suggestions' => [
                    '🔥 Great energy! This is perfect for tackling challenging tasks.',
                    '🔥 Channel this energy into productive activities.',
                    '🔥 Remember to take breaks even when energetic - avoid burnout.',
                    '🔥 Document what\'s working for you today!'
                ]
            ]
        ],
        
        // Screen time suggestions
        'screen_time' => [
            'excessive' => [
                'title' => 'Excessive Screen Time',
                'suggestions' => [
                    '📱 Take a 5-minute eye break every 25 minutes (20-20-20 rule).',
                    '📱 Keep your phone in another room while studying.',
                    '📱 Try screen-free activities: reading, drawing, sports.',
                    '📱 Set a timer when using social media.',
                    '📱 Use grayscale mode on your phone to make it less appealing.'
                ]
            ],
            'high' => [
                'title' => 'High Screen Time',
                'suggestions' => [
                    '📱 Try the 50-10 rule: 50 mins focus, 10 mins screen break.',
                    '📱 Have screen-free meals with family.',
                    '📱 Replace one hour of entertainment with outdoor activity.',
                    '📱 Use apps that track and limit your screen time.'
                ]
            ],
            'imbalanced' => [
                'title' => 'Screen Imbalance',
                'suggestions' => [
                    '⚖️ Your recreational screen time is higher than study time.',
                    '⚖️ Try to balance entertainment with productive activities.',
                    '⚖️ Set specific times for entertainment (e.g., 6-7 PM only).',
                    '⚖️ Reward study sessions with short entertainment breaks.'
                ]
            ],
            'healthy' => [
                'title' => 'Healthy Screen Balance',
                'suggestions' => [
                    '✅ Good screen time management! Keep this balance.',
                    '✅ You\'re using screens mindfully - great job!',
                    '✅ This balance supports both productivity and well-being.'
                ]
            ]
        ],
        
        // Study-related suggestions
        'study' => [
            'low_engagement' => [
                'title' => 'Low Study Engagement',
                'suggestions' => [
                    '📚 Start with just 20 minutes - momentum builds motivation.',
                    '📚 Create a dedicated study space free from distractions.',
                    '📚 Use the Pomodoro technique: 25 mins study, 5 mins break.',
                    '📚 Start with your favorite subject to build momentum.',
                    '📚 Join a study group for accountability and motivation.'
                ]
            ],
            'overload' => [
                'title' => 'Study Overload',
                'suggestions' => [
                    '📚 You\'ve studied a lot! Remember to take breaks.',
                    '📚 Take a 10-minute walk to refresh your brain.',
                    '📚 Stay hydrated and have healthy snacks during long sessions.',
                    '📚 Prioritize tasks - focus on what\'s most important.',
                    '📚 Don\'t forget to sleep - it helps memory consolidation.'
                ]
            ],
            'inconsistent' => [
                'title' => 'Inconsistent Study',
                'suggestions' => [
                    '📚 Create a weekly study schedule and stick to it.',
                    '📚 Study at the same time each day to build routine.',
                    '📚 Set small, achievable daily goals (e.g., "study 30 mins").',
                    '📚 Track your study sessions in a journal.',
                    '📚 Find an accountability partner to check in with.'
                ]
            ],
            'low_completion' => [
                'title' => 'Low Task Completion',
                'suggestions' => [
                    '📚 Break big assignments into smaller, manageable tasks.',
                    '📚 Use a planner to track deadlines and progress.',
                    '📚 Start with the hardest task first (eat the frog).',
                    '📚 Remove distractions before starting work.',
                    '📚 Ask for help if you\'re stuck - don\'t wait too long.'
                ]
            ],
            'productive' => [
                'title' => 'Productive Study!',
                'suggestions' => [
                    '🎓 Great study session! Your consistency pays off.',
                    '🎓 Excellent focus! Keep up this productive habit.',
                    '🎓 You\'re making good progress - celebrate small wins!'
                ]
            ]
        ],
        
        // Social interaction suggestions
        'social' => [
            'isolation' => [
                'title' => 'Social Isolation',
                'suggestions' => [
                    '👥 Reach out to one friend today - even a text helps.',
                    '👥 Join a study group or club at school.',
                    '👥 Schedule regular video calls with friends.',
                    '👥 Participate in class discussions.',
                    '👥 Remember: everyone feels isolated sometimes - you\'re not alone.'
                ]
            ],
            'loneliness' => [
                'title' => 'Loneliness Detected',
                'suggestions' => [
                    '💙 Talk to a family member about how you feel.',
                    '💙 Connect with a school counselor - they\'re here to help.',
                    '💙 Join an online community with similar interests.',
                    '💙 Sometimes helping others helps us feel connected.',
                    '💙 Your feelings are valid - reaching out is a sign of strength.'
                ]
            ],
            'low_family' => [
                'title' => 'Low Family Connection',
                'suggestions' => [
                    '👨‍👩‍👧 Have a meal with family without phones.',
                    '👨‍👩‍👧 Share something interesting you learned today.',
                    '👨‍👩‍👧 Ask family members about their day.',
                    '👨‍👩‍👧 Watch a show or play a game together.',
                    '👨‍👩‍👧 Small conversations build stronger connections.'
                ]
            ],
            'low_friends' => [
                'title' => 'Low Friend Connection',
                'suggestions' => [
                    '👥 Initiate a conversation with a friend.',
                    '👥 Plan a small get-together or study session.',
                    '👥 Check in on friends - ask how they\'re doing.',
                    '👥 Share something you\'re working on.',
                    '👥 Friendships need nurturing - reach out regularly.'
                ]
            ],
            'positive' => [
                'title' => 'Good Social Connection!',
                'suggestions' => [
                    '🌟 Great that you connected with others! Social bonds protect mental health.',
                    '🌟 Your social connections are strong - keep nurturing them.',
                    '🌟 Positive social interactions boost mood and resilience.'
                ]
            ]
        ],
        
        // Mood-specific suggestions
        'mood' => [
            'sad' => [
                'title' => 'Low Mood',
                'suggestions' => [
                    '😔 Do one small thing you usually enjoy, even for 5 minutes.',
                    '😔 Get some sunlight - it boosts vitamin D and mood.',
                    '😔 Move your body - even 10 minutes helps release endorphins.',
                    '😔 Talk to someone you trust about how you feel.',
                    '😔 Write down three good things from today.',
                    '😔 Be kind to yourself - you\'re doing your best.'
                ]
            ],
            'stressed' => [
                'title' => 'Stressed State',
                'suggestions' => [
                    '😰 Take a moment to breathe deeply - 5 slow breaths.',
                    '😰 Break tasks into smaller steps and tackle one at a time.',
                    '😰 Give yourself permission to take breaks.',
                    '😰 Ask for help if you need it - you don\'t have to do everything alone.',
                    '😰 Remember: this feeling is temporary.'
                ]
            ],
            'neutral' => [
                'title' => 'Neutral Mood',
                'suggestions' => [
                    '😐 A neutral day is okay - not every day needs to be amazing.',
                    '😐 Use this balanced state for focused work.',
                    '😐 Try one small thing to boost your mood: listen to music, go outside.',
                    '😐 Check in with yourself - what do you need right now?'
                ]
            ],
            'happy' => [
                'title' => 'Positive Mood',
                'suggestions' => [
                    '😊 Wonderful to see you happy! Savor this feeling.',
                    '😊 Share your positive energy with someone today.',
                    '😊 Use this good mood to tackle something you\'ve been avoiding.',
                    '😊 Notice what contributed to your happiness - do more of that!'
                ]
            ],
            'motivated' => [
                'title' => 'Highly Motivated',
                'suggestions' => [
                    '🔥 Your motivation is inspiring! Use this energy wisely.',
                    '🔥 Set a meaningful goal while you feel this drive.',
                    '🔥 Document what\'s working for you today.',
                    '🔥 Remember this feeling for days when motivation is low.',
                    '🔥 Channel this energy into something that matters to you.'
                ]
            ]
        ],
        
        // Content consumption suggestions
        'content' => [
            'violent' => [
                'title' => 'Violent Content',
                'suggestions' => [
                    '🎬 Violent content can affect your mood and stress levels.',
                    '🎬 Consider balancing with lighter, educational content.',
                    '🎬 Try watching something inspiring or uplifting.',
                    '🎬 Notice how different content makes you feel.',
                    '🎬 Limit exposure before bed - it can affect sleep.'
                ]
            ],
            'horror' => [
                'title' => 'Horror Content',
                'suggestions' => [
                    '🎬 Horror content can increase anxiety and stress.',
                    '🎬 Watch something calming afterward to balance.',
                    '🎬 Avoid horror before sleep - it can cause nightmares.',
                    '🎬 Consider if this content is helping or hurting your mood.'
                ]
            ],
            'educational' => [
                'title' => 'Educational Content',
                'suggestions' => [
                    '📚 Great choice! Educational content stimulates your mind.',
                    '📚 Take notes on interesting things you learn.',
                    '📚 Share what you learned with someone else.',
                    '📚 Balance educational content with rest.'
                ]
            ],
            'entertainment' => [
                'title' => 'Entertainment Content',
                'suggestions' => [
                    '🎬 Entertainment is great for relaxation - just keep it balanced.',
                    '🎬 Set time limits so entertainment doesn\'t replace other activities.',
                    '🎬 Choose content that leaves you feeling good afterward.'
                ]
            ]
        ],
        
        // Physical activity suggestions
        'physical' => [
            'inactive' => [
                'title' => 'Low Physical Activity',
                'suggestions' => [
                    '🏃 Start with just 10 minutes of walking daily.',
                    '🏃 Take stretch breaks between study sessions.',
                    '🏃 Try a 5-minute yoga or stretching video.',
                    '🏃 Use stairs instead of elevator.',
                    '🏃 Movement boosts mood and focus!'
                ]
            ],
            'active' => [
                'title' => 'Good Activity!',
                'suggestions' => [
                    '🌟 Great job staying active! Physical activity boosts mood.',
                    '🌟 Your body thanks you for the movement.',
                    '🌟 Keep this up - consistency matters more than intensity.'
                ]
            ]
        ],
        
        // Wellness suggestions
        'wellness' => [
            'hydration' => [
                'title' => 'Stay Hydrated',
                'suggestions' => [
                    '💧 Keep a water bottle at your desk.',
                    '💧 Set reminders to drink water throughout the day.',
                    '💧 Add fruit slices for flavor if plain water is boring.',
                    '💧 Drink a glass before each meal.',
                    '💧 Hydration affects mood, energy, and concentration.'
                ]
            ],
            'nutrition' => [
                'title' => 'Improve Nutrition',
                'suggestions' => [
                    '🥗 Include protein in breakfast for sustained energy.',
                    '🥗 Snack on fruits, nuts, or yogurt instead of processed foods.',
                    '🥗 Avoid skipping meals - it affects mood and focus.',
                    '🥗 Limit sugary foods that cause energy crashes.',
                    '🥗 Plan healthy snacks for study breaks.'
                ]
            ],
            'breaks' => [
                'title' => 'Take Breaks',
                'suggestions' => [
                    '⏸️ Your brain needs breaks to process information.',
                    '⏸️ Try the Pomodoro technique: 25 mins work, 5 mins break.',
                    '⏸️ Step away from screens during breaks.',
                    '⏸️ Short breaks actually improve productivity.'
                ]
            ]
        ]
    ];
    
    /**
     * Constructor
     * @param PDO $databaseConnection Database connection
     * @param int $userId Current user ID
     */
    public function __construct($databaseConnection, $userId = null) {
        $this->conn = $databaseConnection;
        $this->userId = $userId;
    }
    
    /**
     * Main analysis function - evaluates mood and behavioral data
     * @param array $moodData Contains all mood and behavioral data
     * @return array Complete analysis with summary, risk level, suggestions, and AI message
     */
    public function analyzeMood($moodData) {
        // Extract all data with defaults
        $mood = $moodData['mood'] ?? 'neutral';
        $stress = (int)($moodData['stress_level'] ?? 3);
        $energy = (int)($moodData['energy_level'] ?? 3);
        $notes = $moodData['notes'] ?? '';
        
        // Behavioral data with defaults
        $study_hours = (float)($moodData['study_hours'] ?? 0);
        $homework_completed = (int)($moodData['homework_completed'] ?? 0);
        $subjects_studied = $moodData['subjects_studied'] ?? '';
        
        $watched_tv = (int)($moodData['watched_tv'] ?? 0);
        $tv_hours = (float)($moodData['tv_hours'] ?? 0);
        $tv_content_type = $moodData['tv_content_type'] ?? '';
        
        $mobile_hours = (float)($moodData['mobile_hours'] ?? 0);
        $mobile_purpose = $moodData['mobile_purpose'] ?? '';
        
        $played_games = (int)($moodData['played_games'] ?? 0);
        $game_duration = (float)($moodData['game_duration'] ?? 0);
        
        $sleep_hours = (float)($moodData['sleep_hours'] ?? 8);
        $sleep_quality = $moodData['sleep_quality'] ?? 'good';
        
        $talked_family = (int)($moodData['talked_family'] ?? 0);
        $met_friends = (int)($moodData['met_friends'] ?? 0);
        $felt_lonely = (int)($moodData['felt_lonely'] ?? 0);
        
        // Calculate total screen time
        $total_screen_time = $tv_hours + $mobile_hours + $game_duration;
        
        // Prepare data for pattern detection
        $patternData = [
            'mood' => $mood,
            'stress' => $stress,
            'energy' => $energy,
            'sleep_hours' => $sleep_hours,
            'sleep_quality' => $sleep_quality,
            'total_screen_time' => $total_screen_time,
            'study_hours' => $study_hours,
            'homework_completed' => $homework_completed,
            'talked_family' => $talked_family,
            'met_friends' => $met_friends,
            'felt_lonely' => $felt_lonely,
            'tv_content_type' => $tv_content_type
        ];
        
        // Detect behavioral patterns
        $patterns = $this->detectPatterns($patternData);
        
        // Generate emotional summary
        $emotionalSummary = $this->generateEmotionalSummary($mood, $stress, $energy, $patterns);
        
        // Calculate risk score and level
        $riskScore = $this->calculateRiskScore($mood, $stress, $energy, $patterns, $patternData);
        $riskLevel = $this->getRiskLevel($riskScore);
        
        // Generate personalized suggestions based on detected patterns
        $suggestions = $this->generateSuggestions($mood, $stress, $energy, $patterns, $riskLevel, $patternData);
        
        // Generate AI message for user
        $aiMessage = $this->generateAIMessage($emotionalSummary, $riskLevel, $suggestions, $patterns, $notes, $patternData);
        
        // Prepare analysis text for database
        $analysisText = $this->prepareAnalysisText($emotionalSummary, $patterns, $riskLevel);
        
        // Format suggestions for storage
        $suggestionsText = $this->formatSuggestionsForStorage($suggestions);
        
        return [
            'emotional_summary' => $emotionalSummary,
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'patterns' => $patterns,
            'suggestions' => $suggestions,
            'suggestions_text' => $suggestionsText,
            'ai_message' => $aiMessage,
            'ai_analysis' => $analysisText,
            'ai_suggestions' => $suggestionsText,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Detect behavioral patterns from data
     */
    private function detectPatterns($data) {
        $patterns = [];
        
        // Sleep patterns
        if ($data['sleep_hours'] < 4) {
            $patterns[] = 'critical_sleep_deprivation';
        } elseif ($data['sleep_hours'] < 6) {
            $patterns[] = 'insufficient_sleep';
        } elseif ($data['sleep_hours'] > 10) {
            $patterns[] = 'excessive_sleep';
        }
        
        if (isset($data['sleep_quality']) && $data['sleep_quality'] == 'poor') {
            $patterns[] = 'poor_sleep_quality';
        } elseif (isset($data['sleep_quality']) && $data['sleep_quality'] == 'disturbed') {
            $patterns[] = 'disturbed_sleep';
        } elseif (isset($data['sleep_quality']) && $data['sleep_quality'] == 'good' && $data['sleep_hours'] >= 7) {
            $patterns[] = 'good_sleep';
        }
        
        // Screen time patterns
        if ($data['total_screen_time'] > 8) {
            $patterns[] = 'critical_screen_time';
        } elseif ($data['total_screen_time'] > 6) {
            $patterns[] = 'excessive_screen_time';
        } elseif ($data['total_screen_time'] > 4) {
            $patterns[] = 'high_screen_time';
        } elseif ($data['total_screen_time'] <= 3) {
            $patterns[] = 'healthy_screen_time';
        }
        
        // Check screen-study balance
        if ($data['study_hours'] > 0 && $data['total_screen_time'] > ($data['study_hours'] * 2)) {
            $patterns[] = 'screen_study_imbalance';
        }
        
        // Study patterns
        if ($data['study_hours'] < 1 && $data['homework_completed'] == 0) {
            $patterns[] = 'no_study';
        } elseif ($data['study_hours'] < 2) {
            $patterns[] = 'low_study';
        } elseif ($data['study_hours'] > 5) {
            $patterns[] = 'high_study_load';
        } elseif ($data['study_hours'] >= 2 && $data['study_hours'] <= 5) {
            $patterns[] = 'productive_study';
        }
        
        // Social patterns
        if ($data['felt_lonely']) {
            $patterns[] = 'loneliness';
            
            if (!$data['talked_family'] && !$data['met_friends']) {
                $patterns[] = 'social_isolation';
            }
        }
        
        if ($data['talked_family'] || $data['met_friends']) {
            $patterns[] = 'positive_social';
        }
        
        if (!$data['talked_family'] && !$data['met_friends'] && !$data['felt_lonely']) {
            $patterns[] = 'low_social_contact';
        }
        
        // Content patterns
        if (isset($data['tv_content_type'])) {
            if ($data['tv_content_type'] == 'violent') {
                $patterns[] = 'violent_content';
            } elseif ($data['tv_content_type'] == 'horror') {
                $patterns[] = 'horror_content';
            } elseif ($data['tv_content_type'] == 'educational') {
                $patterns[] = 'educational_content';
            } elseif ($data['tv_content_type'] == 'entertainment') {
                $patterns[] = 'entertainment_content';
            }
        }
        
        // Stress patterns
        if ($data['stress'] >= 5) {
            $patterns[] = 'critical_stress';
        } elseif ($data['stress'] >= 4) {
            $patterns[] = 'high_stress';
        } elseif ($data['stress'] <= 2) {
            $patterns[] = 'low_stress';
        }
        
        // Energy patterns
        if ($data['energy'] <= 2) {
            $patterns[] = 'very_low_energy';
        } elseif ($data['energy'] <= 3) {
            $patterns[] = 'low_energy';
        } elseif ($data['energy'] >= 4) {
            $patterns[] = 'high_energy';
        }
        
        // Mood-specific patterns
        if ($data['mood'] == 'stressed' && $data['stress'] >= 4) {
            $patterns[] = 'stressed_state';
        }
        
        if ($data['mood'] == 'sad' && $data['energy'] <= 2) {
            $patterns[] = 'low_mood_low_energy';
        }
        
        if ($data['mood'] == 'motivated' && $data['energy'] >= 4) {
            $patterns[] = 'motivated_state';
        }
        
        if ($data['mood'] == 'happy') {
            $patterns[] = 'positive_mood';
        }
        
        return $patterns;
    }
    
    /**
     * Generate emotional summary based on mood and patterns
     */
    private function generateEmotionalSummary($mood, $stress, $energy, $patterns) {
        $moodValue = $this->moodValues[$mood] ?? 3;
        $emotionalScore = ($moodValue + $energy) - $stress;
        
        // Base summary on emotional score
        if ($emotionalScore >= 8) {
            $summary = "✨ You're in an excellent state today! High motivation and positive energy.";
        } elseif ($emotionalScore >= 6) {
            $summary = "😊 You're feeling balanced and ready to learn. Great job!";
        } elseif ($emotionalScore >= 4) {
            $summary = "🤔 You seem a bit overwhelmed, but it's manageable. Take it one step at a time.";
        } elseif ($emotionalScore >= 2) {
            $summary = "😔 You appear to be experiencing some stress and low energy today.";
        } else {
            $summary = "💙 You seem quite overwhelmed. Remember it's okay to take a break and ask for help.";
        }
        
        // Add pattern-specific insights
        if (in_array('critical_sleep_deprivation', $patterns)) {
            $summary .= " Your sleep is dangerously low, which severely affects mood and focus.";
        } elseif (in_array('insufficient_sleep', $patterns)) {
            $summary .= " Getting more sleep could help boost your energy and mood.";
        }
        
        if (in_array('social_isolation', $patterns)) {
            $summary .= " You seem isolated today - connecting with others might help you feel better.";
        } elseif (in_array('loneliness', $patterns)) {
            $summary .= " You mentioned feeling lonely - reaching out to someone could help.";
        }
        
        if (in_array('excessive_screen_time', $patterns) || in_array('critical_screen_time', $patterns)) {
            $summary .= " Screen time is high - consider taking breaks and reducing evening screen use.";
        }
        
        if (in_array('high_stress', $patterns) || in_array('critical_stress', $patterns)) {
            $summary .= " Your stress is elevated. Try deep breathing or a short walk.";
        }
        
        if (in_array('very_low_energy', $patterns)) {
            $summary .= " Your energy is very low. Stay hydrated and consider a light snack.";
        }
        
        if (in_array('productive_study', $patterns)) {
            $summary .= " Great study session! Your consistency pays off.";
        }
        
        if (in_array('positive_social', $patterns)) {
            $summary .= " Wonderful that you connected with others today!";
        }
        
        if (in_array('good_sleep', $patterns)) {
            $summary .= " Excellent sleep - this sets you up for success!";
        }
        
        return $summary;
    }
    
    /**
     * Calculate risk score based on multiple factors
     */
    private function calculateRiskScore($mood, $stress, $energy, $patterns, $additional) {
        $riskScore = 0;
        
        // Mood factors (max 25 points)
        if (in_array($mood, ['sad', 'stressed'])) {
            $riskScore += 10;
        }
        
        if ($stress >= 5) {
            $riskScore += 20;
        } elseif ($stress >= 4) {
            $riskScore += 15;
        } elseif ($stress >= 3) {
            $riskScore += 5;
        }
        
        if ($energy <= 2) {
            $riskScore += 15;
        } elseif ($energy <= 3) {
            $riskScore += 5;
        }
        
        // Sleep factors (max 30 points)
        if ($additional['sleep_hours'] < 4) {
            $riskScore += 30;
        } elseif ($additional['sleep_hours'] < 5) {
            $riskScore += 25;
        } elseif ($additional['sleep_hours'] < 6) {
            $riskScore += 20;
        } elseif ($additional['sleep_hours'] < 7) {
            $riskScore += 10;
        }
        
        if (isset($additional['sleep_quality']) && $additional['sleep_quality'] == 'poor') {
            $riskScore += 15;
        } elseif (isset($additional['sleep_quality']) && $additional['sleep_quality'] == 'disturbed') {
            $riskScore += 10;
        }
        
        // Screen time factors (max 20 points)
        if ($additional['total_screen_time'] > 8) {
            $riskScore += 20;
        } elseif ($additional['total_screen_time'] > 6) {
            $riskScore += 15;
        } elseif ($additional['total_screen_time'] > 4) {
            $riskScore += 10;
        } elseif ($additional['total_screen_time'] > 2) {
            $riskScore += 5;
        }
        
        // Social factors (max 25 points)
        if ($additional['felt_lonely']) {
            $riskScore += 20;
        }
        
        if (!$additional['talked_family'] && !$additional['met_friends']) {
            $riskScore += 10;
        }
        
        // Study factors (max 15 points)
        if ($additional['study_hours'] < 1 && $additional['homework_completed'] == 0) {
            $riskScore += 15;
        } elseif ($additional['study_hours'] < 2) {
            $riskScore += 5;
        }
        
        // Content factors (max 10 points)
        if (isset($additional['tv_content_type']) && in_array($additional['tv_content_type'], ['violent', 'horror'])) {
            $riskScore += 10;
        }
        
        // Pattern multipliers (adds extra for dangerous combinations)
        if (in_array('social_isolation', $patterns) && in_array($mood, ['sad', 'stressed'])) {
            $riskScore += 15; // Isolation + negative mood
        }
        
        if (in_array('excessive_screen_time', $patterns) && in_array('insufficient_sleep', $patterns)) {
            $riskScore += 15; // Screen time affecting sleep
        }
        
        if (in_array('critical_sleep_deprivation', $patterns) && $stress >= 4) {
            $riskScore += 20; // No sleep + high stress
        }
        
        // Check for 3+ consecutive sad entries (if we have user ID)
        if ($this->userId) {
            $consecutiveSad = $this->checkConsecutiveSadEntries();
            if ($consecutiveSad >= 3) {
                $riskScore += 25;
            } elseif ($consecutiveSad == 2) {
                $riskScore += 15;
            }
        }
        
        return min(100, $riskScore); // Cap at 100
    }
    
    /**
     * Check for consecutive sad/stressed entries
     */
    private function checkConsecutiveSadEntries() {
        if (!$this->userId) {
            return 0;
        }
        
        try {
            $stmt = $this->conn->prepare("
                SELECT mood FROM mood_entries 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute([$this->userId]);
            $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $count = 0;
            foreach ($recent as $entry) {
                if (in_array($entry['mood'], ['sad', 'stressed'])) {
                    $count++;
                } else {
                    break;
                }
            }
            
            return $count;
        } catch (Exception $e) {
            error_log("Error checking consecutive sad entries: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get risk level based on score
     */
    private function getRiskLevel($score) {
        if ($score >= 70) {
            return "🔴 Critical";
        } elseif ($score >= 50) {
            return "🔴 High";
        } elseif ($score >= 30) {
            return "🟡 Medium";
        } elseif ($score >= 15) {
            return "🟢 Low";
        } else {
            return "🟢 Minimal";
        }
    }
    
    /**
     * Generate personalized suggestions based on analysis
     */
    private function generateSuggestions($mood, $stress, $energy, $patterns, $riskLevel, $data) {
        $suggestions = [
            'priority' => [],
            'general' => [],
            'positive' => []
        ];
        
        // ===== PRIORITY SUGGESTIONS (Critical issues first) =====
        
        // Critical sleep deprivation
        if (in_array('critical_sleep_deprivation', $patterns)) {
            $suggestions['priority'] = array_merge(
                $suggestions['priority'],
                $this->getSuggestionsFromLibrary('sleep', 'critical', 2)
            );
        }
        // Critical stress
        elseif (in_array('critical_stress', $patterns)) {
            $suggestions['priority'] = array_merge(
                $suggestions['priority'],
                $this->getSuggestionsFromLibrary('stress', 'critical', 2)
            );
        }
        // Social isolation with loneliness
        elseif (in_array('social_isolation', $patterns) && in_array('loneliness', $patterns)) {
            $suggestions['priority'] = array_merge(
                $suggestions['priority'],
                $this->getSuggestionsFromLibrary('social', 'loneliness', 2)
            );
        }
        // Critical screen time
        elseif (in_array('critical_screen_time', $patterns)) {
            $suggestions['priority'] = array_merge(
                $suggestions['priority'],
                $this->getSuggestionsFromLibrary('screen_time', 'excessive', 2)
            );
        }
        
        // ===== PATTERN-BASED SUGGESTIONS =====
        
        // Sleep suggestions
        if (in_array('insufficient_sleep', $patterns)) {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('sleep', 'insufficient', 2)
            );
        }
        if (in_array('poor_sleep_quality', $patterns) || in_array('disturbed_sleep', $patterns)) {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('sleep', 'poor_quality', 2)
            );
        }
        if (in_array('excessive_sleep', $patterns)) {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('sleep', 'excessive', 2)
            );
        }
        
        // Stress suggestions
        if (in_array('high_stress', $patterns)) {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('stress', 'high', 2)
            );
        }
        
        // Energy suggestions
        if (in_array('very_low_energy', $patterns)) {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('energy', 'very_low', 2)
            );
        } elseif (in_array('low_energy', $patterns)) {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('energy', 'low', 2)
            );
        }
        
        // Screen time suggestions
        if (in_array('excessive_screen_time', $patterns)) {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('screen_time', 'excessive', 2)
            );
        } elseif (in_array('high_screen_time', $patterns)) {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('screen_time', 'high', 2)
            );
        }
        if (in_array('screen_study_imbalance', $patterns)) {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('screen_time', 'imbalanced', 2)
            );
        }
        
        // Study suggestions
        if (in_array('no_study', $patterns) || in_array('low_study', $patterns)) {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('study', 'low_engagement', 2)
            );
        }
        if (in_array('high_study_load', $patterns)) {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('study', 'overload', 2)
            );
        }
        
        // Social suggestions
        if (in_array('social_isolation', $patterns)) {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('social', 'isolation', 2)
            );
        } elseif (in_array('loneliness', $patterns)) {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('social', 'loneliness', 2)
            );
        } elseif (in_array('low_social_contact', $patterns)) {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('social', 'low_friends', 1)
            );
        }
        
        // Mood-specific suggestions
        if ($mood == 'sad') {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('mood', 'sad', 2)
            );
        } elseif ($mood == 'stressed') {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('mood', 'stressed', 2)
            );
        } elseif ($mood == 'neutral') {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('mood', 'neutral', 1)
            );
        }
        
        // Content suggestions
        if (in_array('violent_content', $patterns)) {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('content', 'violent', 1)
            );
        } elseif (in_array('horror_content', $patterns)) {
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('content', 'horror', 1)
            );
        }
        
        // Add wellness suggestions if no priority issues
        if (empty($suggestions['priority']) && count($suggestions['general']) < 3) {
            // Add hydration tip
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('wellness', 'hydration', 1)
            );
            
            // Add break tip
            $suggestions['general'] = array_merge(
                $suggestions['general'],
                $this->getSuggestionsFromLibrary('wellness', 'breaks', 1)
            );
        }
        
        // ===== POSITIVE REINFORCEMENTS =====
        if (in_array('good_sleep', $patterns)) {
            $suggestions['positive'] = array_merge(
                $suggestions['positive'],
                $this->getSuggestionsFromLibrary('sleep', 'good', 1)
            );
        }
        if (in_array('productive_study', $patterns)) {
            $suggestions['positive'] = array_merge(
                $suggestions['positive'],
                $this->getSuggestionsFromLibrary('study', 'productive', 1)
            );
        }
        if (in_array('positive_social', $patterns)) {
            $suggestions['positive'] = array_merge(
                $suggestions['positive'],
                $this->getSuggestionsFromLibrary('social', 'positive', 1)
            );
        }
        if (in_array('positive_mood', $patterns) || $mood == 'happy') {
            $suggestions['positive'] = array_merge(
                $suggestions['positive'],
                $this->getSuggestionsFromLibrary('mood', 'happy', 1)
            );
        }
        if (in_array('motivated_state', $patterns) || $mood == 'motivated') {
            $suggestions['positive'] = array_merge(
                $suggestions['positive'],
                $this->getSuggestionsFromLibrary('mood', 'motivated', 1)
            );
        }
        if (in_array('healthy_screen_time', $patterns)) {
            $suggestions['positive'] = array_merge(
                $suggestions['positive'],
                $this->getSuggestionsFromLibrary('screen_time', 'healthy', 1)
            );
        }
        if (in_array('low_stress', $patterns)) {
            $suggestions['positive'] = array_merge(
                $suggestions['positive'],
                $this->getSuggestionsFromLibrary('stress', 'low', 1)
            );
        }
        
        // Remove duplicates
        $suggestions['priority'] = array_unique($suggestions['priority']);
        $suggestions['general'] = array_unique($suggestions['general']);
        $suggestions['positive'] = array_unique($suggestions['positive']);
        
        // Limit counts
        $suggestions['priority'] = array_slice($suggestions['priority'], 0, 3);
        $suggestions['general'] = array_slice($suggestions['general'], 0, 4);
        $suggestions['positive'] = array_slice($suggestions['positive'], 0, 2);
        
        return $suggestions;
    }
    
    /**
     * Get suggestions from library
     */
    private function getSuggestionsFromLibrary($category, $subcategory, $count = 1) {
        if (!isset($this->suggestionLibrary[$category][$subcategory])) {
            return [];
        }
        
        $all = $this->suggestionLibrary[$category][$subcategory]['suggestions'];
        shuffle($all);
        return array_slice($all, 0, $count);
    }
    
    /**
     * Format suggestions for database storage
     */
    private function formatSuggestionsForStorage($suggestions) {
        $formatted = [];
        
        if (!empty($suggestions['priority'])) {
            $formatted = array_merge($formatted, $suggestions['priority']);
        }
        
        if (!empty($suggestions['general'])) {
            $formatted = array_merge($formatted, $suggestions['general']);
        }
        
        if (!empty($suggestions['positive'])) {
            $formatted = array_merge($formatted, $suggestions['positive']);
        }
        
        return implode(' | ', array_slice($formatted, 0, 5));
    }
    
    /**
     * Generate user-friendly AI message
     */
    private function generateAIMessage($emotionalSummary, $riskLevel, $suggestions, $patterns, $note, $data) {
        $message = $emotionalSummary . "\n\n";
        
        // Add priority suggestions first
        if (!empty($suggestions['priority'])) {
            $message .= "⚠️ **Important:**\n";
            foreach ($suggestions['priority'] as $sug) {
                $message .= "• " . $sug . "\n";
            }
            $message .= "\n";
        }
        
        // Add general suggestions
        if (!empty($suggestions['general'])) {
            $message .= "💡 **Suggestions for today:**\n";
            $suggestionsToShow = array_slice($suggestions['general'], 0, 3);
            foreach ($suggestionsToShow as $sug) {
                $message .= "• " . $sug . "\n";
            }
            $message .= "\n";
        }
        
        // Add positive reinforcements
        if (!empty($suggestions['positive'])) {
            $message .= "🌟 **What's going well:**\n";
            foreach ($suggestions['positive'] as $pos) {
                $message .= "• " . $pos . "\n";
            }
            $message .= "\n";
        }
        
        // Add risk level context
        if (strpos($riskLevel, 'Critical') !== false) {
            $message .= "🔴 **You seem to be going through a really tough time.**\n";
            $message .= "It's completely okay to ask for help. Consider talking to:\n";
            $message .= "   • Your parents or guardians\n";
            $message .= "   • A school counselor\n";
            $message .= "   • A trusted teacher\n";
            $message .= "   • A friend\n\n";
            $message .= "You're not alone, and people care about you.\n\n";
        } elseif (strpos($riskLevel, 'High') !== false) {
            $message .= "🟡 **You're experiencing some challenges right now.**\n";
            $message .= "Small steps can make a big difference. Focus on one suggestion at a time.\n\n";
        } elseif (strpos($riskLevel, 'Medium') !== false) {
            $message .= "🟢 You're doing okay, but a few small changes could help you feel better.\n\n";
        } else {
            $message .= "💚 You're doing great! Keep up these healthy habits.\n\n";
        }
        
        // Acknowledge their note if they wrote one
        if (!empty($note)) {
            $message .= "💭 Thank you for sharing your thoughts. Writing down how you feel is a healthy habit.\n";
        }
        
        return $message;
    }
    
    /**
     * Prepare analysis text for database storage
     */
    private function prepareAnalysisText($emotionalSummary, $patterns, $riskLevel) {
        $text = $emotionalSummary;
        
        if (!empty($patterns)) {
            $patternNames = array_map(function($p) {
                return str_replace('_', ' ', $p);
            }, $patterns);
            $text .= " Detected patterns: " . implode(", ", $patternNames) . ".";
        }
        
        $text .= " Risk level: " . $riskLevel;
        
        return $text;
    }
    
    /**
     * Get mood history for a user
     * @param int $limit Number of entries to return
     * @return array Mood history with analysis
     */
    public function getMoodHistory($limit = 10) {
        if (!$this->userId) {
            return [];
        }
        
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM mood_entries 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$this->userId, $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching mood history: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get mood trends for analytics
     * @param string $period 'week', 'month', 'all'
     * @return array Mood trends data
     */
    public function getMoodTrends($period = 'week') {
        if (!$this->userId) {
            return [];
        }
        
        try {
            $interval = 'INTERVAL 7 DAY';
            if ($period == 'month') {
                $interval = 'INTERVAL 30 DAY';
            } elseif ($period == 'all') {
                $interval = 'INTERVAL 365 DAY';
            }
            
            $stmt = $this->conn->prepare("
                SELECT 
                    DATE(created_at) as date,
                    mood,
                    stress_level,
                    energy_level,
                    sleep_hours,
                    study_hours,
                    risk_level
                FROM mood_entries 
                WHERE user_id = ? 
                    AND created_at >= DATE_SUB(NOW(), $interval)
                ORDER BY date ASC
            ");
            $stmt->execute([$this->userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching mood trends: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get wellness tips based on mood patterns
     * @param array $moodData Current mood data
     * @return array Wellness tips
     */
    public function getWellnessTips($moodData = []) {
        $tips = [];
        
        $mood = $moodData['mood'] ?? 'neutral';
        $stress = $moodData['stress_level'] ?? 3;
        $energy = $moodData['energy_level'] ?? 3;
        
        if ($mood == 'stressed' || $mood == 'sad') {
            $tips = [
                "Take short breaks between study sessions",
                "Practice deep breathing for 5 minutes",
                "Reach out to friends or family",
                "Consider speaking with a counselor if feelings persist"
            ];
        } elseif ($stress > 3.5) {
            $tips = [
                "Try meditation or mindfulness exercises",
                "Get some fresh air and sunlight",
                "Stay hydrated and eat well",
                "Get at least 7-8 hours of sleep"
            ];
        } elseif ($energy < 2.5) {
            $tips = [
                "Light exercise can boost energy",
                "Take power naps (15-20 minutes)",
                "Eat energy-boosting foods like fruits and nuts",
                "Stay hydrated throughout the day"
            ];
        } else {
            $tips = [
                "Keep up the great work!",
                "Share your positive energy with others",
                "Maintain your healthy habits",
                "Set new goals to stay motivated"
            ];
        }
        
        return $tips;
    }
    
    /**
     * Generate comprehensive student report for admin
     * @param int $studentId Student ID to analyze
     * @return array Complete report with insights and recommendations
     */
    public function generateStudentReport($studentId) {
        try {
            // Get recent mood entries
            $stmt = $this->conn->prepare("
                SELECT * FROM mood_entries 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 30
            ");
            $stmt->execute([$studentId]);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($entries)) {
                return [
                    'status' => 'insufficient_data',
                    'message' => 'Not enough mood data to generate report'
                ];
            }
            
            // Calculate averages
            $avgStress = array_sum(array_column($entries, 'stress_level')) / count($entries);
            $avgEnergy = array_sum(array_column($entries, 'energy_level')) / count($entries);
            $avgSleep = array_sum(array_column($entries, 'sleep_hours')) / count($entries);
            $avgStudy = array_sum(array_column($entries, 'study_hours')) / count($entries);
            
            // Count moods
            $moodCounts = [];
            $riskCounts = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
            
            foreach ($entries as $entry) {
                $moodCounts[$entry['mood']] = ($moodCounts[$entry['mood']] ?? 0) + 1;
                if (isset($entry['risk_level'])) {
                    $level = $entry['risk_level'];
                    if (strpos($level, 'Critical') !== false) $riskCounts['critical']++;
                    elseif (strpos($level, 'High') !== false) $riskCounts['high']++;
                    elseif (strpos($level, 'Medium') !== false) $riskCounts['medium']++;
                    elseif (strpos($level, 'Low') !== false || strpos($level, 'Minimal') !== false) $riskCounts['low']++;
                }
            }
            
            // Find most common mood
            arsort($moodCounts);
            $mostCommonMood = key($moodCounts) ?: 'neutral';
            
            // Calculate trends
            $trend = $this->calculateTrend($entries);
            
            // Generate insights
            $insights = [];
            if ($avgStress > 4) {
                $insights[] = "⚠️ Stress levels are critically high. Immediate intervention recommended.";
            } elseif ($avgStress > 3.5) {
                $insights[] = "⚠️ Stress levels are consistently high. Recommend stress management techniques.";
            }
            
            if ($avgSleep < 5) {
                $insights[] = "😴 Sleep is critically low. This significantly impacts health and academic performance.";
            } elseif ($avgSleep < 6) {
                $insights[] = "😴 Sleep is very low. Aim for 7-8 hours.";
            } elseif ($avgSleep < 7) {
                $insights[] = "😐 Sleep is below recommended levels. Aim for 7-8 hours.";
            }
            
            if ($avgStudy < 1) {
                $insights[] = "📚 Study engagement is very low. May need academic motivation support.";
            }
            
            if (($riskCounts['critical'] ?? 0) > 0) {
                $insights[] = "🔴 Critical risk days detected. Schedule immediate counselor intervention.";
            } elseif (($riskCounts['high'] ?? 0) > 3) {
                $insights[] = "🔴 Multiple high-risk days detected. Schedule counselor check-in soon.";
            }
            
            // Generate recommendations
            $recommendations = [];
            if ($avgStress > 3.5) {
                $recommendations[] = "Practice daily mindfulness or deep breathing exercises";
                $recommendations[] = "Break tasks into smaller, manageable chunks";
            }
            if ($avgSleep < 7) {
                $recommendations[] = "Establish a consistent sleep schedule";
                $recommendations[] = "Avoid screens 1 hour before bedtime";
            }
            if ($avgStudy < 2) {
                $recommendations[] = "Set small daily study goals (start with 20 minutes)";
                $recommendations[] = "Use a timer for focused study sessions";
            }
            if (($riskCounts['high'] ?? 0) > 0 || ($riskCounts['critical'] ?? 0) > 0) {
                $recommendations[] = "Schedule regular check-ins with school counselor";
                $recommendations[] = "Encourage open communication with parents";
            }
            
            return [
                'student_id' => $studentId,
                'period' => '30 days',
                'total_entries' => count($entries),
                'averages' => [
                    'stress' => round($avgStress, 1),
                    'energy' => round($avgEnergy, 1),
                    'sleep' => round($avgSleep, 1),
                    'study' => round($avgStudy, 1)
                ],
                'most_common_mood' => $mostCommonMood,
                'mood_emoji' => $this->moodEmojis[$mostCommonMood] ?? '😊',
                'mood_distribution' => $moodCounts,
                'risk_distribution' => $riskCounts,
                'trend' => $trend,
                'insights' => $insights,
                'recommendations' => $recommendations,
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Error generating student report: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate mood trend direction
     */
    private function calculateTrend($entries) {
        if (count($entries) < 5) {
            return 'insufficient_data';
        }
        
        $firstHalf = array_slice($entries, 0, floor(count($entries)/2));
        $secondHalf = array_slice($entries, floor(count($entries)/2));
        
        $firstAvgStress = array_sum(array_column($firstHalf, 'stress_level')) / count($firstHalf);
        $secondAvgStress = array_sum(array_column($secondHalf, 'stress_level')) / count($secondHalf);
        
        $firstAvgMood = array_sum(array_map(function($e) {
            return $this->moodValues[$e['mood']] ?? 3;
        }, $firstHalf)) / count($firstHalf);
        
        $secondAvgMood = array_sum(array_map(function($e) {
            return $this->moodValues[$e['mood']] ?? 3;
        }, $secondHalf)) / count($secondHalf);
        
        $stressDiff = $secondAvgStress - $firstAvgStress;
        $moodDiff = $secondAvgMood - $firstAvgMood;
        
        if ($stressDiff > 0.8 && $moodDiff < -0.8) {
            return 'declining_rapidly';
        } elseif ($stressDiff > 0.5 || $moodDiff < -0.5) {
            return 'declining';
        } elseif ($stressDiff < -0.8 && $moodDiff > 0.8) {
            return 'improving_rapidly';
        } elseif ($stressDiff < -0.5 || $moodDiff > 0.5) {
            return 'improving';
        } else {
            return 'stable';
        }
    }
    
    /**
     * Create alert for high-risk situations
     * @param int $userId Student ID
     * @param string $alertType Type of alert
     * @param int $riskScore Risk score
     * @param string $message Alert message
     * @return bool Success status
     */
    public function createAlert($userId, $alertType, $riskScore, $message) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO mood_alerts (user_id, alert_type, risk_score, message, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            return $stmt->execute([$userId, $alertType, $riskScore, $message]);
        } catch (Exception $e) {
            error_log("Error creating alert: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get active alerts for a user
     * @param int $userId Student ID
     * @param bool $unreadOnly Only get unread alerts
     * @return array Alerts
     */
    public function getUserAlerts($userId, $unreadOnly = true) {
        try {
            $sql = "SELECT * FROM mood_alerts WHERE user_id = ?";
            if ($unreadOnly) {
                $sql .= " AND is_read = 0";
            }
            $sql .= " ORDER BY created_at DESC LIMIT 10";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching alerts: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark alert as read
     * @param int $alertId Alert ID
     * @return bool Success status
     */
    public function markAlertRead($alertId) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE mood_alerts SET is_read = 1 WHERE id = ?
            ");
            return $stmt->execute([$alertId]);
        } catch (Exception $e) {
            error_log("Error marking alert read: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Helper function for quick AI analysis
 * @param string $mood Mood value
 * @param int $stress Stress level
 * @param int $energy Energy level
 * @param string $note Optional note
 * @param int|null $userId User ID
 * @return string AI message
 */
function getAIMessage($mood, $stress, $energy, $note = '', $userId = null) {
    global $pdo;
    
    $aiEngine = new AIMoodEngine($pdo, $userId);
    $result = $aiEngine->analyzeMood([
        'mood' => $mood,
        'stress_level' => $stress,
        'energy_level' => $energy,
        'notes' => $note
    ]);
    
    return $result['ai_message'];
}

/**
 * Helper function to get wellness tips
 * @param array $moodData Current mood data
 * @return array Wellness tips
 */
function getWellnessTips($moodData = []) {
    global $pdo;
    
    $aiEngine = new AIMoodEngine($pdo);
    return $aiEngine->getWellnessTips($moodData);
}

/**
 * Helper function to check if user is at risk
 * @param int $userId User ID
 * @return array Risk assessment
 */
function checkUserRisk($userId) {
    global $pdo;
    
    $aiEngine = new AIMoodEngine($pdo, $userId);
    
    // Get latest mood entry
    $stmt = $pdo->prepare("SELECT * FROM mood_entries WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $latest = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$latest) {
        return [
            'has_risk' => false,
            'message' => 'No mood data available'
        ];
    }
    
    $result = $aiEngine->analyzeMood([
        'mood' => $latest['mood'],
        'stress_level' => $latest['stress_level'],
        'energy_level' => $latest['energy_level'],
        'notes' => $latest['notes']
    ]);
    
    return [
        'has_risk' => (strpos($result['risk_level'], 'High') !== false || strpos($result['risk_level'], 'Critical') !== false),
        'risk_level' => $result['risk_level'],
        'risk_score' => $result['risk_score'],
        'message' => $result['emotional_summary'],
        'suggestions' => $result['suggestions'] ?? []
    ];
}
?>