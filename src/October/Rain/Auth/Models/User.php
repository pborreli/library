<?php namespace October\Rain\Auth\Models;

use Hash;
use DateTime;
use October\Rain\Database\Model;
use October\Rain\Auth\Hash\HasherBase;

/**
 * User model
 */
class User extends Model
{
    /**
     * @var string The table associated with the model.
     */
    protected $table = 'users';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'email' => 'required|between:3,64|email|unique:users',
        'password' => 'required:create|between:2,32|confirmed',
        'password_confirmation' => 'required_with:password|between:2,32'
    ];

    /**
     * @var array Relations
     */
    public $belongsToMany = [
        'groups' => ['October\Rain\Auth\Group', 'table' => 'users_groups']
    ];

    /**
     * @var array The attributes that should be hidden for arrays.
     */
    protected $hidden = ['password', 'reset_password_code', 'activation_code', 'persist_code'];

    /**
     * @var array The attributes that aren't mass assignable.
     */
    protected $guarded = ['reset_password_code', 'activation_code', 'persist_code'];

    /**
     * @var array List of attribute names which should be hashed using the Bcrypt hashing algorithm.
     */
    protected $hashable = ['password', 'persist_code'];

    /**
     * @var array List of attribute names which should not be saved to the database.
     */
    protected $purgeable = ['password_confirmation'];

    /**
     * @var array List of attribute names which are json encoded and decoded from the database.
     */
    protected $jsonable = ['permissions'];

    /**
     * Allowed permissions values.
     *
     * Possible options:
     *   -1 => Deny (adds to array, but denies regardless of user's group).
     *    0 => Remove.
     *    1 => Add.
     *
     * @var array
     */
    protected $allowedPermissionsValues = [-1, 0, 1];

    /**
     * @var string The login attribute.
     */
    protected static $loginAttribute = 'email';

    /**
     * @var array The user groups.
     */
    protected $userGroups;

    /**
     * @var array The user merged permissions.
     */
    protected $mergedPermissions;

    /**
     * @return mixed Returns the user's ID.
     */
    public function getId()
    {
        return $this->getKey();
    }

    /**
     * @return string Returns the name for the user's login.
     */
    public function getLoginName()
    {
        return static::$loginAttribute;
    }

    /**
     * @return mixed Returns the user's login.
     */
    public function getLogin()
    {
        return $this->{$this->getLoginName()};
    }

    /**
     * @return string Returns the user's full name.
     */
    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Mutator to protect the password from being reset to null.
     */
    public function setPasswordAttribute($value)
    {
        if ($this->exists && empty($value))
            unset($this->attributes['password']);
        else {
            $this->attributes['password'] = $value;

            // Password has changed, log out all users
            $this->attributes['persist_code'] = null;
        }
    }

    /**
     * @return bool Check if the user is activated.
     */
    public function isActivated()
    {
        return (bool) $this->activated;
    }

    /**
     * Get mutator for giving the activated property.
     * @param mixed $activated
     * @return bool
     */
    public function getActivatedAttribute($activated)
    {
        return (bool) $activated;
    }

    /**
     * Validate the permissions when set.
     * @param array $permissions
     * @return void
     */
    public function setPermissionsAttribute($permissions)
    {
        $permissions = json_decode($permissions, true);
        foreach ($permissions as $permission => &$value) {
            if (!in_array($value = (int)$value, $this->allowedPermissionsValues))
                throw new \InvalidArgumentException("Invalid value [$value] for permission [$permission] given.");

            if ($value === 0)
                unset($permissions[$permission]);
        }

        $this->attributes['permissions'] = (!empty($permissions)) ? json_encode($permissions) : '';
    }

    /**
     * Checks if the user is a super user - has access to everything regardless of permissions.
     * @return bool
     */
    public function isSuperUser()
    {
        return $this->hasPermission('superuser');
    }

    /**
     * Delete the user
     * @return bool
     */
    public function delete()
    {
        $this->groups()->detach();
        return parent::delete();
    }

    /**
     * Gets a code for when the user is persisted to a cookie or session which identifies the user.
     * @return string
     */
    public function getPersistCode()
    {
        $this->persist_code = $this->getRandomString();

        // Our code got hashed
        $persistCode = $this->persist_code;

        $this->forceSave();

        return $persistCode;
    }

    /**
     * Checks the given persist code.
     * @param string $persistCode
     * @return bool
     */
    public function checkPersistCode($persistCode)
    {
        if (!$persistCode)
            return false;

        return $persistCode == $this->persist_code;
    }

    /**
     * Get an activation code for the given user.
     * @return string
     */
    public function getActivationCode()
    {
        $this->activation_code = $activationCode = $this->getRandomString();

        $this->forceSave();

        return $activationCode;
    }

    /**
     * Attempts to activate the given user by checking the activate code. If the user is activated already, an Exception is thrown.
     * @param string $activationCode
     * @return bool
     */
    public function attemptActivation($activationCode)
    {
        if ($this->activated)
            throw new \Exception('Cannot attempt activation on an already activated user.');

        if ($activationCode == $this->activation_code) {
            $this->activation_code = null;
            $this->activated = true;
            $this->activated_at = new DateTime;
            return $this->forceSave();
        }

        return false;
    }

    /**
     * Checks the password passed matches the user's password.
     * @param string $password
     * @return bool
     */
    public function checkPassword($password)
    {
        return Hash::check($password, $this->password);
    }

    /**
     * Get a reset password code for the given user.
     * @return string
     */
    public function getResetPasswordCode()
    {
        $this->reset_password_code = $resetCode = $this->getRandomString();
        $this->forceSave();
        return $resetCode;
    }

    /**
     * Checks if the provided user reset password code is valid without actually resetting the password.
     * @param string $resetCode
     * @return bool
     */
    public function checkResetPasswordCode($resetCode)
    {
        return ($this->reset_password_code == $resetCode);
    }

    /**
     * Attemps to reset a user's password by matching the reset code generated with the user's.
     * @param string $resetCode
     * @param string $newPassword
     * @return bool
     */
    public function attemptResetPassword($resetCode, $newPassword)
    {
        if ($this->checkResetPasswordCode($resetCode)) {
            $this->password = $newPassword;
            $this->reset_password_code = null;
            return $this->forceSave();
        }

        return false;
    }

    /**
     * Wipes out the data associated with resetting a password.
     * @return void
     */
    public function clearResetPassword()
    {
        if ($this->reset_password_code) {
            $this->reset_password_code = null;
            $this->forceSave();
        }
    }

    /**
     * Returns an array of groups which the given user belongs to.
     * @return array
     */
    public function getGroups()
    {
        if (!$this->userGroups)
            $this->userGroups = $this->groups()->get();

        return $this->userGroups;
    }

    /**
     * Adds the user to the given group.
     * @param Group $group
     * @return bool
     */
    public function addGroup($group)
    {
        if (!$this->inGroup($group)) {
            $this->groups()->attach($group);
            $this->userGroups = null;
        }

        return true;
    }

    /**
     * Removes the user from the given group.
     * @param Group $group
     * @return bool
     */
    public function removeGroup($group)
    {
        if ($this->inGroup($group)) {
            $this->groups()->detach($group);
            $this->userGroups = null;
        }

        return true;
    }

    /**
     * See if the user is in the given group.
     * @param Group $group
     * @return bool
     */
    public function inGroup($group)
    {
        foreach ($this->getGroups() as $_group) {
            if ($_group->getId() == $group->getId())
                return true;
        }

        return false;
    }

    /**
     * Returns an array of merged permissions for each group the user is in.
     * @return array
     */
    public function getMergedPermissions()
    {
        if (!$this->mergedPermissions) {
            $permissions = [];

            foreach ($this->getGroups() as $group) {
                if (!is_array($group->permissions))
                    continue;

                $permissions = array_merge($permissions, $group->permissions);
            }

            if (is_array($this->permissions))
                $permissions = array_merge($permissions, $this->permissions);

            $this->mergedPermissions = $permissions;
        }

        return $this->mergedPermissions;
    }

    /**
     * See if a user has access to the passed permission(s).
     * Permissions are merged from all groups the user belongs to
     * and then are checked against the passed permission(s).
     *
     * If multiple permissions are passed, the user must
     * have access to all permissions passed through, unless the
     * "all" flag is set to false.
     *
     * Super users have access no matter what.
     *
     * @param  string|array  $permissions
     * @param  bool  $all
     * @return bool
     */
    public function hasAccess($permissions, $all = true)
    {
        if ($this->isSuperUser())
            return true;

        return $this->hasPermission($permissions, $all);
    }

    /**
     * See if a user has access to the passed permission(s).
     * Permissions are merged from all groups the user belongs to
     * and then are checked against the passed permission(s).
     *
     * If multiple permissions are passed, the user must
     * have access to all permissions passed through, unless the
     * "all" flag is set to false.
     *
     * Super users DON'T have access no matter what.
     *
     * @param  string|array  $permissions
     * @param  bool  $all
     * @return bool
     */
    public function hasPermission($permissions, $all = true)
    {
        $mergedPermissions = $this->getMergedPermissions();

        if (!is_array($permissions))
            $permissions = (array)$permissions;

        foreach ($permissions as $permission) {
            // We will set a flag now for whether this permission was
            // matched at all.
            $matched = true;

            // Now, let's check if the permission ends in a wildcard "*" symbol.
            // If it does, we'll check through all the merged permissions to see
            // if a permission exists which matches the wildcard.
            if ((strlen($permission) > 1) && ends_with($permission, '*')) {
                $matched = false;

                foreach ($mergedPermissions as $mergedPermission => $value) {
                    // Strip the '*' off the end of the permission.
                    $checkPermission = substr($permission, 0, -1);

                    // We will make sure that the merged permission does not
                    // exactly match our permission, but starts wtih it.
                    if ($checkPermission != $mergedPermission && starts_with($mergedPermission, $checkPermission) && $value == 1) {
                        $matched = true;
                        break;
                    }
                }
            }
            elseif ((strlen($permission) > 1) && starts_with($permission, '*')) {
                $matched = false;

                foreach ($mergedPermissions as $mergedPermission => $value) {
                    // Strip the '*' off the beginning of the permission.
                    $checkPermission = substr($permission, 1);

                    // We will make sure that the merged permission does not
                    // exactly match our permission, but ends with it.
                    if ($checkPermission != $mergedPermission && ends_with($mergedPermission, $checkPermission) && $value == 1) {
                        $matched = true;
                        break;
                    }
                }
            }
            else {
                $matched = false;

                foreach ($mergedPermissions as $mergedPermission => $value) {
                    // This time check if the mergedPermission ends in wildcard "*" symbol.
                    if ((strlen($mergedPermission) > 1) && ends_with($mergedPermission, '*')) {
                        $matched = false;

                        // Strip the '*' off the end of the permission.
                        $checkMergedPermission = substr($mergedPermission, 0, -1);

                        // We will make sure that the merged permission does not
                        // exactly match our permission, but starts wtih it.
                        if ($checkMergedPermission != $permission && starts_with($permission, $checkMergedPermission) && $value == 1) {
                            $matched = true;
                            break;
                        }
                    }

                    // Otherwise, we'll fallback to standard permissions checking where
                    // we match that permissions explicitly exist.
                    elseif ($permission == $mergedPermission && $mergedPermissions[$permission] == 1) {
                        $matched = true;
                        break;
                    }
                }
            }

            // Now, we will check if we have to match all
            // permissions or any permission and return
            // accordingly.
            if ($all === true && $matched === false) {
                return false;
            }
            elseif ($all === false && $matched === true) {
                return true;
            }
        }

        if ($all === false)
            return false;

        return true;
    }

    /**
     * Returns if the user has access to any of the given permissions.
     * @param  array  $permissions
     * @return bool
     */
    public function hasAnyAccess(array $permissions)
    {
        return $this->hasAccess($permissions, false);
    }

    /**
     * Records a login for the user.
     *
     * @return void
     */
    public function recordLogin()
    {
        $this->last_login = new DateTime;
        $this->forceSave();
    }

    /**
     * Generate a random string. If your server has
     * @return string
     */
    public function getRandomString($length = 42)
    {
        // We'll check if the user has OpenSSL installed with PHP. If they do
        // we'll use a better method of getting a random string. Otherwise, we'll
        // fallback to a reasonably reliable method.
        if (function_exists('openssl_random_pseudo_bytes')) {
            // We generate twice as many bytes here because we want to ensure we have
            // enough after we base64 encode it to get the length we need because we
            // take out the "/", "+", and "=" characters.
            $bytes = openssl_random_pseudo_bytes($length * 2);

            // We want to stop execution if the key fails because, well, that is bad.
            if ($bytes === false)
                throw new \RuntimeException('Unable to generate random string.');

            return substr(str_replace(array('/', '+', '='), '', base64_encode($bytes)), 0, $length);
        }

        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
    }

    /**
     * Get the attributes that should be converted to dates.
     * @return array
     */
    public function getDates()
    {
        return array_merge(parent::getDates(), array('activated_at', 'last_login'));
    }

    /**
     * Convert the model instance to an array.
     * @return array
     */
    public function toArray()
    {
        $result = parent::toArray();

        if (isset($result['activated']))
            $result['activated'] = $this->getActivatedAttribute($result['activated']);

        if (isset($result['permissions']))
            $result['permissions'] = $this->getPermissionsAttribute($result['permissions']);

        if (isset($result['suspended_at']))
            $result['suspended_at'] = $result['suspended_at']->format('Y-m-d H:i:s');

        return $result;
    }
}