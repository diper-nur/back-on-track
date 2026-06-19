<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   LANGUAGE SETTINGS
========================= */
$allowedLangs = ["en", "ru", "kz"];

/* Save selected language */
if (isset($_GET["lang"]) && in_array($_GET["lang"], $allowedLangs, true)) {
    $_SESSION["lang"] = $_GET["lang"];

    setcookie(
        "lang",
        $_GET["lang"],
        time() + (86400 * 30),
        "/"
    );
}

/* Language priority: SESSION -> COOKIE -> RU */
if (isset($_SESSION["lang"]) && in_array($_SESSION["lang"], $allowedLangs, true)) {
    $lang = $_SESSION["lang"];
} elseif (isset($_COOKIE["lang"]) && in_array($_COOKIE["lang"], $allowedLangs, true)) {
    $lang = $_COOKIE["lang"];
    $_SESSION["lang"] = $lang;
} else {
    $lang = "ru";
}

/* =========================
   TRANSLATIONS
========================= */
$translations = [

    "en" => [
        "dashboard" => "Dashboard",
        "join_class" => "Join Class",
        "tasks" => "Tasks",
        "materials" => "Materials",
        "study_materials" => "Study Materials",
        "ai" => "AI Recommendations",
        "ai_recommendations" => "AI Recommendations",
        "logout" => "Logout",

        "class" => "Class",
        "join_class_first" => "Please join a class first.",

        "my_tasks" => "My Tasks",
        "Your_Tasks" => "Your Tasks",

        "total_tasks" => "Total Tasks",
        "completed" => "Completed",
        "pending" => "Pending",
        "progress" => "Progress",

        "task" => "Task",
        "task_title" => "Task title",
        "task_description" => "Task description",
        "task_notes" => "Task notes",
        "description" => "Description",
        "notes" => "Notes",
        "deadline" => "Deadline",
        "status" => "Status",
        "actions" => "Actions",
        "add_task" => "Add Task",
        "no_tasks" => "No tasks yet.",
        "mark_done" => "Mark as completed",
        "delete" => "Delete",
        "overdue" => "Overdue",

        "upcoming_deadlines" => "Upcoming Deadlines",
        "due" => "Due",
        "dismiss" => "Dismiss",

        "add_subject" => "Add Subject",
        "add_subject_placeholder" => "Add Subject (e.g. Physics)",
        "select_subject" => "Select Subject",
        "upload_material" => "Upload Material",
        "material_title" => "Material title",
        "upload" => "Upload",
        "files" => "Files",
        "study" => "Study",
        "loading_ai" => "Loading AI...",
        "ai_explanation" => "AI Explanation",
        "ai_tasks" => "AI Tasks",
        "add_tasks" => "Add Tasks",
        "no_materials" => "No materials yet.",
        "no_files_attached" => "No files attached",
        "study_unavailable" => "Study unavailable",
        "supported_files" => "Supported files: PDF, DOCX, PPTX, XLSX, TXT, PNG, JPG, JPEG.",
        "uploaded" => "Uploaded",

        "invalid_subject_selected" => "Invalid subject selected.",
        "subject_name_empty" => "Subject name cannot be empty.",
        "subject_exists" => "Subject already exists.",
        "subject_added" => "Subject added!",
        "subject_error" => "Error adding subject.",
        "provide_title" => "Please provide a title.",
        "choose_file" => "Please choose at least one file.",
        "material_uploaded" => "Material uploaded successfully. Files attached:",
        "material_upload_failed" => "Material was not uploaded because no valid files were attached.",
        "failed_create_material" => "Failed to create material.",

        "ai_recovery_plan" => "AI Recovery Plan",
        "generate_recovery_plan" => "Generate Recovery Plan",
        "latest_ai_plan" => "Latest AI Plan",
        "day" => "Day",
        "time" => "Time",

        "enter_class_code" => "Enter Class Code",
        "joined_successfully" => "Joined successfully!",
        "already_joined" => "You already joined this class.",
        "invalid_class_code" => "Invalid class code.",

        "login" => "Login",
        "register" => "Register",
        "email" => "Email",
        "password" => "Password",
        "name" => "Name"
    ],

    "ru" => [
        "dashboard" => "Главная",
        "join_class" => "Присоединиться к классу",
        "tasks" => "Задания",
        "materials" => "Материалы",
        "study_materials" => "Учебные материалы",
        "ai" => "AI-рекомендации",
        "ai_recommendations" => "AI-рекомендации",
        "logout" => "Выйти",

        "class" => "Класс",
        "join_class_first" => "Сначала присоединитесь к классу.",

        "my_tasks" => "Мои задания",
        "Your_Tasks" => "Ваши задания",

        "total_tasks" => "Всего заданий",
        "completed" => "Выполнено",
        "pending" => "В процессе",
        "progress" => "Прогресс",

        "task" => "Задание",
        "task_title" => "Название задания",
        "task_description" => "Описание задания",
        "task_notes" => "Заметки",
        "description" => "Описание",
        "notes" => "Заметки",
        "deadline" => "Дедлайн",
        "status" => "Статус",
        "actions" => "Действия",
        "add_task" => "Добавить задание",
        "no_tasks" => "Заданий пока нет.",
        "mark_done" => "Отметить как выполненное",
        "delete" => "Удалить",
        "overdue" => "Просрочено",

        "upcoming_deadlines" => "Ближайшие дедлайны",
        "due" => "Срок",
        "dismiss" => "Закрыть",

        "add_subject" => "Добавить предмет",
        "add_subject_placeholder" => "Добавить предмет, например физика",
        "select_subject" => "Выберите предмет",
        "upload_material" => "Загрузить материал",
        "material_title" => "Название материала",
        "upload" => "Загрузить",
        "files" => "Файлы",
        "study" => "Изучить",
        "loading_ai" => "AI загружается...",
        "ai_explanation" => "AI-объяснение",
        "ai_tasks" => "AI-задания",
        "add_tasks" => "Добавить задания",
        "no_materials" => "Материалов пока нет.",
        "no_files_attached" => "Файлы не прикреплены",
        "study_unavailable" => "Изучение недоступно",
        "supported_files" => "Поддерживаемые файлы: PDF, DOCX, PPTX, XLSX, TXT, PNG, JPG, JPEG.",
        "uploaded" => "Загружено",

        "invalid_subject_selected" => "Выбран неверный предмет.",
        "subject_name_empty" => "Название предмета не может быть пустым.",
        "subject_exists" => "Такой предмет уже существует.",
        "subject_added" => "Предмет добавлен!",
        "subject_error" => "Ошибка при добавлении предмета.",
        "provide_title" => "Укажите название материала.",
        "choose_file" => "Выберите хотя бы один файл.",
        "material_uploaded" => "Материал успешно загружен. Прикреплено файлов:",
        "material_upload_failed" => "Материал не был загружен, потому что нет подходящих файлов.",
        "failed_create_material" => "Не удалось создать материал.",

        "ai_recovery_plan" => "AI-план восстановления",
        "generate_recovery_plan" => "Создать AI-план",
        "latest_ai_plan" => "Последний AI-план",
        "day" => "День",
        "time" => "Время",

        "enter_class_code" => "Введите код класса",
        "joined_successfully" => "Вы успешно присоединились!",
        "already_joined" => "Вы уже присоединились к этому классу.",
        "invalid_class_code" => "Неверный код класса.",

        "login" => "Войти",
        "register" => "Регистрация",
        "email" => "Почта",
        "password" => "Пароль",
        "name" => "Имя"
    ],

    "kz" => [
        "dashboard" => "Басты бет",
        "join_class" => "Сыныпқа қосылу",
        "tasks" => "Тапсырмалар",
        "materials" => "Материалдар",
        "study_materials" => "Оқу материалдары",
        "ai" => "AI ұсыныстар",
        "ai_recommendations" => "AI ұсыныстар",
        "logout" => "Шығу",

        "class" => "Сынып",
        "join_class_first" => "Алдымен сыныпқа қосылыңыз.",

        "my_tasks" => "Менің тапсырмаларым",
        "Your_Tasks" => "Сіздің тапсырмаларыңыз",

        "total_tasks" => "Барлық тапсырмалар",
        "completed" => "Орындалды",
        "pending" => "Орындалуда",
        "progress" => "Прогресс",

        "task" => "Тапсырма",
        "task_title" => "Тапсырма атауы",
        "task_description" => "Тапсырма сипаттамасы",
        "task_notes" => "Ескертпелер",
        "description" => "Сипаттама",
        "notes" => "Ескертпелер",
        "deadline" => "Мерзім",
        "status" => "Статус",
        "actions" => "Әрекеттер",
        "add_task" => "Тапсырма қосу",
        "no_tasks" => "Әзірше тапсырмалар жоқ.",
        "mark_done" => "Орындалды деп белгілеу",
        "delete" => "Жою",
        "overdue" => "Мерзімі өтті",

        "upcoming_deadlines" => "Жақын дедлайндар",
        "due" => "Мерзімі",
        "dismiss" => "Жабу",

        "add_subject" => "Пән қосу",
        "add_subject_placeholder" => "Пән қосыңыз, мысалы физика",
        "select_subject" => "Пәнді таңдаңыз",
        "upload_material" => "Материал жүктеу",
        "material_title" => "Материал атауы",
        "upload" => "Жүктеу",
        "files" => "Файлдар",
        "study" => "Оқу",
        "loading_ai" => "AI жүктелуде...",
        "ai_explanation" => "AI түсіндірмесі",
        "ai_tasks" => "AI тапсырмалары",
        "add_tasks" => "Тапсырмаларды қосу",
        "no_materials" => "Әзірше материалдар жоқ.",
        "no_files_attached" => "Файлдар тіркелмеген",
        "study_unavailable" => "Оқу қолжетімсіз",
        "supported_files" => "Қолдау көрсетілетін файлдар: PDF, DOCX, PPTX, XLSX, TXT, PNG, JPG, JPEG.",
        "uploaded" => "Жүктелді",

        "invalid_subject_selected" => "Таңдалған пән дұрыс емес.",
        "subject_name_empty" => "Пән атауы бос болмауы керек.",
        "subject_exists" => "Бұл пән бұрыннан бар.",
        "subject_added" => "Пән қосылды!",
        "subject_error" => "Пән қосу кезінде қате шықты.",
        "provide_title" => "Материал атауын енгізіңіз.",
        "choose_file" => "Кемінде бір файл таңдаңыз.",
        "material_uploaded" => "Материал сәтті жүктелді. Тіркелген файлдар:",
        "material_upload_failed" => "Материал жүктелмеді, себебі жарамды файлдар тіркелмеген.",
        "failed_create_material" => "Материал жасау мүмкін болмады.",

        "ai_recovery_plan" => "AI қалпына келтіру жоспары",
        "generate_recovery_plan" => "AI жоспар құру",
        "latest_ai_plan" => "Соңғы AI жоспар",
        "day" => "Күн",
        "time" => "Уақыт",

        "enter_class_code" => "Сынып кодын енгізіңіз",
        "joined_successfully" => "Сәтті қосылдыңыз!",
        "already_joined" => "Сіз бұл сыныпқа бұрын қосылғансыз.",
        "invalid_class_code" => "Сынып коды қате.",

        "login" => "Кіру",
        "register" => "Тіркелу",
        "email" => "Пошта",
        "password" => "Құпия сөз",
        "name" => "Аты"
    ]
];

/* =========================
   TRANSLATION FUNCTION
========================= */
function t($key) {
    global $translations, $lang;

    return $translations[$lang][$key]
        ?? $translations["en"][$key]
        ?? $key;
}
?>
