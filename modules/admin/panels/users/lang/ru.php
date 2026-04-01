<?php

return array(
    // Module name
    'admin-users.name' => 'Пользователи',

    // List view
    'admin-users.title' => 'Пользователи',
    'admin-users.new' => 'Новый пользователь',
    'admin-users.edit_user' => 'Редактирование пользователя',
    'admin-users.no_users' => 'Пользователей пока нет.',

    // Table columns
    'admin-users.field.username' => 'Имя пользователя',
    'admin-users.field.email' => 'Email',
    'admin-users.field.password' => 'Пароль',
    'admin-users.field.role' => 'Роль',
    'admin-users.field.status' => 'Статус',
    'admin-users.field.updated' => 'Обновлено',
    'admin-users.field.actions' => 'Действия',

    // Roles
    'admin-users.role.admin' => 'Администратор',
    'admin-users.role.editor' => 'Редактор',
    'admin-users.role.viewer' => 'Читатель',

    // Statuses
    'admin-users.status.active' => 'Активен',
    'admin-users.status.inactive' => 'Неактивен',
    'admin-users.status.banned' => 'Заблокирован',

    // Actions
    'admin-users.create' => 'Создать пользователя',
    'admin-users.update' => 'Сохранить',
    'admin-users.delete_confirm' => 'Вы уверены, что хотите удалить этого пользователя?',

    // Form
    'admin-users.password_help' => 'Оставьте пустым, чтобы сохранить текущий пароль.',
    'admin-users.password_required' => 'Пароль обязателен для новых пользователей.',
    'admin-users.username_help' => 'Нельзя изменить после создания.',

    // Errors
    'admin-users.not_found' => 'Пользователь не найден.',
    'admin-users.create_error' => 'Не удалось создать пользователя. Имя пользователя может быть занято или пароль не указан.',
    'admin-users.access_denied' => 'Доступ запрещён. Требуются права администратора.',
    'admin-users.is_you' => 'Вы',
);
