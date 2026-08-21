<?php require_once('security.php'); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
  * {
    font-family: 'Poppins', sans-serif;
  }

  .navbar {
    background: rgba(10, 61, 98, 0.95);
    backdrop-filter: blur(10px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    padding: 0.8rem 1.5rem;
    z-index: 999;
  }

  .navbar-brand {
    font-weight: bold;
    color: white !important;
    display: flex;
    align-items: center;
    font-size: 1.3rem;
    flex-shrink: 0;
  }

  .navbar-brand img {
    height: 48px;
    margin-left: 12px;
    border-radius: 8px;
    transition: all 0.3s ease;
  }

  .brand-text {
    font-family: 'Noto Nastaliq Urdu', serif;
    font-size: 2.4rem;
    color: #fff;
    line-height: 1.3;
  }

  .nav-link {
    color: white !important;
    font-weight: 600;
    margin-right: 12px;
    transition: all 0.3s;
    font-size: 1.15rem;
    padding: 10px 16px !important;
    display: flex;
    align-items: center;
  }

  .nav-link i {
    font-size: 1.1rem;
    margin-right: 8px;
    flex-shrink: 0;
  }

  .nav-link:hover {
    color: #cce7ff !important;
    transform: translateY(-2px);
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
  }

  .dropdown-menu {
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    padding: 8px 0;
    border: none;
    background: #fff;
  }

  /* ========== INCREASED FONT SIZE FOR SUB-LINKS (Laptop/Desktop) ========== */
  .dropdown-menu .dropdown-item {
    font-weight: 500;
    padding: 12px 24px;
    font-size: 1.2rem !important;
    transition: all 0.2s;
    display: flex;
    align-items: center;
  }

  .dropdown-menu .dropdown-item i {
    font-size: 1.2rem !important;
    margin-right: 12px;
    width: 24px;
    color: #0a3d62;
    flex-shrink: 0;
  }

  .dropdown-menu .dropdown-item:hover {
    background: #eaf4ff;
    color: #0a3d62;
    padding-left: 28px;
  }

  .btn-logout {
    background: #c0392b !important;
    color: white !important;
    font-weight: 600;
    border-radius: 10px;
    padding: 10px 20px !important;
    font-size: 1.05rem;
    transition: all 0.3s;
    flex-shrink: 0;
  }

  .btn-logout:hover {
    background: #e74c3c !important;
    transform: translateY(-2px);
  }

  /* ================= BASE LAYOUT (EN & UR SHARE THIS) ================= */
  /* Mobile: Toggler left, Logo right, Scrollable menu */
  @media (max-width: 991px) {
    .navbar .container-fluid {
      justify-content: flex-start;
    }
    .navbar{
      padding: 0 0 0 0;
    }
    .navbar-brand {
      margin-left: auto !important;
      margin-right: 0 !important;
    }
    
    /* Smaller logo on mobile */
    .navbar-brand img {
      height: 32px !important;
      margin-left: 8px;
    }
    
    .brand-text {
      font-size: 1.8rem !important;
    }
    
    .navbar-toggler {
      margin-right: 0;
      margin-left: 0;
    }
    
    /* Make navbar collapse scrollable - without fixed positioning */
    .navbar-collapse {
      background-color: #0a3d62;
      padding: 1rem;
      border-radius: 12px;
      margin-top: 10px;
      max-height: 70vh;
      overflow-y: auto;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }
    
    /* Custom scrollbar for mobile menu */
    .navbar-collapse::-webkit-scrollbar {
      width: 5px;
    }
    
    .navbar-collapse::-webkit-scrollbar-track {
      background: #0a3d62;
      border-radius: 10px;
    }
    
    .navbar-collapse::-webkit-scrollbar-thumb {
      background: #cce7ff;
      border-radius: 10px;
    }

    .nav-link {
      font-size: 1.2rem;
      padding: 12px 16px !important;
    }

    .dropdown-menu {
      background-color: #0f4a72;
      position: static !important;
      float: none !important;
      width: 100% !important;
      margin-top: 0 !important;
      transform: none !important;
      border-radius: 8px;
    }

    /* ========== INCREASED FONT SIZE FOR SUB-LINKS (Mobile) ========== */
    .dropdown-menu .dropdown-item {
      color: white !important;
      font-size: 1.3rem !important;
      padding: 14px 24px;
    }
    
    .dropdown-menu .dropdown-item i {
      color: white;
      font-size: 1.3rem !important;
    }

    .btn-logout {
      margin-top: 10px;
      text-align: center;
      width: 100%;
      justify-content: center;
    }
    
    /* Fix for dropdown toggle on mobile */
    .dropdown-toggle::after {
      float: right;
      margin-top: 8px;
    }
  }

  /* Desktop: Logo RIGHT, Nav LEFT */
  @media (min-width: 992px) {
    .navbar .container-fluid {
      flex-direction: row-reverse;
      justify-content: flex-end;
    }
    
    .navbar-brand {
      margin-left: 0;
      margin-right: 0;
    }
    
    .navbar-toggler {
      display: none;
    }
    
    .navbar-collapse {
      display: flex !important;
      flex-basis: auto;
      margin-right: auto;
    }
  }

  /* ================= RTL MODE (URDU ONLY) ================= */
  <?php if ($lang == 'ur'): ?>
  
  /* 🔒 LOCK LAYOUT: Prevent flexbox from reversing element order */
  .navbar .container-fluid, 
  .navbar-collapse, 
  .navbar-nav, 
  .navbar-brand {
    direction: ltr !important;
  }

  /* 📝 TEXT RTL: Apply direction only to readable text */
  .brand-text, 
  .nav-link span, 
  .dropdown-item span, 
  .btn-logout span {
    direction: rtl;
    text-align: right;
  }

  /* 📍 MOBILE RTL: EXPLICITLY force logo to stay on the right */
  @media (max-width: 991px) {
    .navbar-brand {
      margin-left: auto !important;
      margin-right: 0 !important;
    }
    
    .navbar-brand img {
      margin-left: 8px;
    }
    
    .navbar-collapse {
      direction: rtl;
    }
    
    .dropdown-toggle::after {
      float: left !important;
    }
  }

  /* 🖥️ DESKTOP RTL: Keep logo on right edge */
  @media (min-width: 992px) {
    .navbar-brand {
      margin-left: 0 !important;
      margin-right: 0 !important;
    }
    
    .navbar-brand img {
      margin-left: 12px;
      margin-right: 0 !important;
    }
  }

  /* 🔄 ICON SPACING: Swap margins for correct RTL visual flow */
  .nav-link i, .btn-logout i {
    margin-right: 0;
    margin-left: 8px;
  }
  .dropdown-menu .dropdown-item i {
    margin-right: 0;
    margin-left: 12px;
  }

  /* 📂 DROPDOWN ALIGNMENT */
  .dropdown-menu {
    left: auto;
    right: 0;
    text-align: right;
    direction: rtl;
  }

  /* ✅ INCREASE DROPDOWN WIDTH FOR URDU DESKTOP/LAPTOP - Only this change */
  @media (min-width: 992px) {
    .dropdown-menu {
      min-width: 380px !important;
      width: max-content !important;
      max-width: 450px !important;
    }
  }

  <?php endif; ?>
</style>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
  <div class="container-fluid">
    <!-- Toggler (leftmost on mobile) -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Logo + Name -->
    <a class="navbar-brand" href="index.php">
      <div class="brand-text">المدرسہ الفاروقیہ</div>
      
    </a>

    <!-- Nav Items -->
    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        
        <!-- Logout -->
        <li class="nav-item">
          <a class="nav-link btn btn-logout" href="logout.php">
            <i class="fas fa-sign-out-alt"></i><span>لاگ آؤٹ | Logout</span>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="index.php">
            <i class="fas fa-home"></i><span>ہوم | Home</span>
          </a>
        </li>

        <!-- Students -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="fas fa-users"></i><span>طلبہ | Students</span>
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="student_registration.php"><i class="fas fa-user-plus"></i><span>اندراج | Register</span></a></li>
            <li><a class="dropdown-item" href="withdrawn_students.php"><i class="fas fa-user-minus"></i><span>اخراج | Withdrawal</span></a></li>
            <li><a class="dropdown-item" href="fee_card_create_monthly.php"><i class="fas fa-credit-card"></i><span>فیس کارڈ | Create Fee Cards</span></a></li>
            <li><a class="dropdown-item" href="student_feecards.php"><i class="fas fa-print"></i><span>فیس کارڈ | Print Fee Cards</span></a></li>
            <li><a class="dropdown-item" href="feeremainders.php"><i class="fas fa-print"></i><span> کلاس فیس کے واجبات
 | Class Fee Dues</span></a></li>
            <li><a class="dropdown-item" href="roles.php"><i class="fas fa-user-tag"></i><span>کردار | Roles</span></a></li>
          </ul>
        </li>

        <!-- Settings -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="fas fa-cogs"></i><span>ترتیبات | Settings</span>
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="village_council.php"><i class="fas fa-map-marker-alt"></i><span>دیہی کونسل | Villages</span></a></li>
            <li><a class="dropdown-item" href="courses.php"><i class="fas fa-book"></i><span>کورسز | Courses</span></a></li>
            <li><a class="dropdown-item" href="sessions.php"><i class="fas fa-calendar-alt"></i><span>سیشن | Sessions</span></a></li>
            <li><a class="dropdown-item" href="classes.php"><i class="fas fa-chalkboard"></i><span>کلاسیں | Classes</span></a></li>
            <li><a class="dropdown-item" href="fee_types.php"><i class="fas fa-tag"></i><span>فیس اقسام | Fee Types</span></a></li>
            <li><a class="dropdown-item" href="course_fee.php"><i class="fas fa-chart-line"></i><span>فیس ڈھانچہ | Fee Structure</span></a></li>
            <li><a class="dropdown-item" href="account_head.php"><i class="fas fa-heading"></i><span>اکاؤنٹ ہیڈ | Account Heads</span></a></li>
            <li><a class="dropdown-item" href="accounts.php"><i class="fas fa-building"></i><span>فراہم کنندگان | Accounts</span></a></li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="fas fa-file-alt"></i><span>امتحانی نظم و نسق|  Exam Management</span>
          </a>
           <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="exam_types.php"><i class="fas fa-file-invoice"></i><span>امتحانات کی اقسام| Exam Types</span></a></li>
            <li><a class="dropdown-item" href="exams.php"><i class="fas fa-calendar-alt"></i><span>امتحانات | Exams</span></a></li>
            <li><a class="dropdown-item" href="question_types.php"><i class="fas fa-list-ul"></i><span>سوالات کی اقسام| Question Types</span></a></li>
            
            <li><a class="dropdown-item" href="class_question_types.php"><i class="fas fa-comments"></i><span>کلاس سوالات
 | Class Questions</span></a></li>
            <li><a class="dropdown-item" href="result_entry.php"><i class="fas fa-edit"></i><span>نتائج کا اندراج
| Result Entry</span></a></li>
            <li><a class="dropdown-item" href="view_result.php"><i class="fas fa-poll"></i><span>نتائج کا منظر
| Result View</span></a></li>
            
            

            
          </ul>
        </li>
        <!-- Accounts -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="fas fa-calculator"></i><span>اکاؤنٹس | Accounts</span>
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="expense.php"><i class="fas fa-money-bill-wave"></i><span>اخراجات | Expenses</span></a></li>
            <li><a class="dropdown-item" href="expense_heads.php"><i class="fas fa-search-dollar"></i><span>اخراجات ہیڈ | Expense Heads</span></a></li>
          </ul>
        </li>

        <!-- Reports -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="fas fa-chart-line"></i><span>رپورٹس | Reports</span>
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="student_classwise_report.php"><i class="fas fa-users-class"></i><span>مالیاتی | طلبہ کی جماعت وار رپورٹ</span></a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var currentPage = window.location.pathname.split('/').pop();
  
  document.querySelectorAll('.nav-link, .dropdown-item').forEach(function(link) {
    if (link.getAttribute('href') === currentPage) {
      link.classList.add('active');
      if (link.classList.contains('dropdown-item')) {
        link.closest('.dropdown').querySelector('.dropdown-toggle').classList.add('active');
      }
    }
  });
});
</script>