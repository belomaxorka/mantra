<?php

return array(
    // Module name
    'admin-users.name' => 'Users',

    // List view
    'admin-users.title' => 'Users',
    'admin-users.new' => 'New User',
    'admin-users.edit_user' => 'Edit User',
    'admin-users.no_users' => 'No users yet.',

    // Table columns
    'admin-users.field.username' => 'Username',
    'admin-users.field.email' => 'Email',
    'admin-users.field.password' => 'Password',
    'admin-users.field.role' => 'Role',
    'admin-users.field.status' => 'Status',
    'admin-users.field.updated' => 'Updated',
    'admin-users.field.actions' => 'Actions',

    // Roles
    'admin-users.role.admin' => 'Admin',
    'admin-users.role.editor' => 'Editor',
    'admin-users.role.viewer' => 'Viewer',

    // Statuses
    'admin-users.status.active' => 'Active',
    'admin-users.status.inactive' => 'Inactive',
    'admin-users.status.banned' => 'Banned',

    // Actions
    'admin-users.create' => 'Create User',
    'admin-users.update' => 'Update User',
    'admin-users.delete_confirm' => 'Are you sure you want to delete this user?',

    // Form
    'admin-users.password_help' => 'Leave empty to keep current password.',
    'admin-users.password_required' => 'Password is required for new users.',
    'admin-users.username_help' => 'Cannot be changed after creation.',

    // Errors
    'admin-users.not_found' => 'User not found.',
    'admin-users.create_error' => 'Failed to create user. Username may already exist or password is missing.',
    'admin-users.access_denied' => 'Access denied. Administrator privileges required.',
    'admin-users.is_you' => 'You',
);
