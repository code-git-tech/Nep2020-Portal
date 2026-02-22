<?php
// Get recent activity
$stmt = $pdo->prepare("
    (SELECT 'video' as type, v.title, v.course_id, c.title as course_title, xp.created_at as date
     FROM xp_transactions xp
     JOIN videos v ON xp.source_id = v.id
     JOIN courses c ON v.course_id = c.id
     WHERE xp.student_id = ? AND xp.source = 'video'
     ORDER BY xp.created_at DESC
     LIMIT 3)
    UNION ALL
    (SELECT 'test' as type, t.title, t.course_id, c.title as course_title, xp.created_at as date
     FROM xp_transactions xp
     JOIN tests t ON xp.source_id = t.id
     JOIN courses c ON t.course_id = c.id
     WHERE xp.student_id = ? AND xp.source = 'test'
     ORDER BY xp.created_at DESC
     LIMIT 3)
    UNION ALL
    (SELECT 'certificate' as type, c.title, c.id, c.title as course_title, cert.issued_date as date
     FROM certificates cert
     JOIN courses c ON cert.course_id = c.id
     WHERE cert.student_id = ?
     ORDER BY cert.issued_date DESC
     LIMIT 3)
    ORDER BY date DESC
    LIMIT 5
");
$stmt->execute([$student_id, $student_id, $student_id]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Recent Activity Feed -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-6 border-b border-gray-100">
        <h2 class="text-xl font-bold text-gray-800">Recent Activity</h2>
    </div>
    
    <div class="divide-y divide-gray-100">
        <?php if(empty($activities)): ?>
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full mx-auto mb-3 flex items-center justify-center">
                    <i class="fas fa-history text-gray-400 text-xl"></i>
                </div>
                <p class="text-gray-500">No recent activity</p>
                <p class="text-sm text-gray-400 mt-1">Start learning to see your activity here</p>
            </div>
        <?php else: ?>
            <?php foreach($activities as $activity): ?>
                <div class="p-4 hover:bg-gray-50 transition">
                    <div class="flex items-start space-x-3">
                        <!-- Activity Icon -->
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center
                                <?= $activity['type'] == 'video' ? 'bg-blue-100' : ($activity['type'] == 'test' ? 'bg-purple-100' : 'bg-yellow-100') ?>">
                                <i class="fas fa-<?= $activity['type'] == 'video' ? 'play' : ($activity['type'] == 'test' ? 'file-alt' : 'award') ?> 
                                    <?= $activity['type'] == 'video' ? 'text-blue-600' : ($activity['type'] == 'test' ? 'text-purple-600' : 'text-yellow-600') ?>">
                                </i>
                            </div>
                        </div>
                        
                        <!-- Activity Content -->
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-800">
                                <?php if($activity['type'] == 'video'): ?>
                                    Watched "<?= htmlspecialchars($activity['title']) ?>"
                                <?php elseif($activity['type'] == 'test'): ?>
                                    Completed "<?= htmlspecialchars($activity['title']) ?>" test
                                <?php else: ?>
                                    Earned certificate for "<?= htmlspecialchars($activity['title']) ?>"
                                <?php endif; ?>
                            </p>
                            <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($activity['course_title']) ?></p>
                            <p class="text-xs text-gray-400 mt-2">
                                <i class="far fa-clock mr-1"></i><?= date('h:i A', strtotime($activity['date'])) ?>
                            </p>
                        </div>
                        
                        <!-- XP Badge (if applicable) -->
                        <?php if($activity['type'] != 'certificate'): ?>
                            <span class="px-2 py-1 bg-green-100 text-green-600 text-xs rounded-full">+100 XP</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- View All Link -->
    <div class="p-4 border-t border-gray-100">
        <a href="#" class="block text-center text-sm text-blue-600 hover:text-blue-700 font-medium">
            View All Activity
            <i class="fas fa-arrow-right ml-2 text-xs"></i>
        </a>
    </div>
</div>