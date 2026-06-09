<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to LearnLink</title>
    <style>
        /* Inline CSS for email compatibility */
        body {margin:0;padding:0;background-color:#f5f8fa;font-family:'Inter',sans-serif;}
        .container {max-width:600px;margin:auto;background:#fff;padding:20px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);}
        h1 {color:#2c3e50;}
        p {color:#34495e;font-size:16px;line-height:1.5;}
        .btn {display:inline-block;padding:12px 24px;margin:20px 0;background:linear-gradient(90deg,#6a11cb,#2575fc);color:#fff;border-radius:8px;text-decoration:none;font-weight:600;}
        .footer {color:#7f8c8d;font-size:14px;}
        .small {color:#95a5a6;font-size:12px;}
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to LearnLink, {{ $user->name }}!</h1>
        @if($user->role === 'teacher')
            <p>Welcome, educator! LearnLink empowers you to share knowledge, manage your courses, and connect with eager students. Start creating your classes and reach a wider audience.</p>
        @elseif($user->role === 'student')
            <p>Welcome, learner! Discover top teachers, book personalized sessions, and enroll in courses that match your goals. Let our AI learning assistant guide you every step of the way.</p>
        @else
            <p>We're thrilled to have you on board. LearnLink connects you with top teachers, lets you book sessions, enroll in courses, and offers an AI learning assistant to guide your journey.</p>
        @endif
        <a href="{{ url('/') }}" class="btn">Explore LearnLink</a>
        <p class="footer">If you have any questions, reply to this email or reach out to our support.</p>
        <hr style="border:none;border-top:1px solid #ecf0f1;margin:30px 0;">
        <p class="small">© {{ date('Y') }} LearnLink. All rights reserved.</p>
    </div>
</body>
</html>