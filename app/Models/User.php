<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Lab404\Impersonate\Models\Impersonate;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    use Notifiable, Impersonate, LogsActivity;

    protected static $logAttributes = ['id', 'name', 'is_admin', 'email', 'campaigns', 'companies'];

    const ROLE_USER = 'user';
    const ROLE_ADMIN = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'last_name',
        'email',
        'timezone',
        'phone_number',
        'password',
        'is_admin',
        'username'
    ];

    protected $casts = [
        'config' => 'array'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function activate($companyId)
    {
        $rel = $this->invitations()->where('company_id', $companyId)->firstOrFail();
        $rel->is_active = true;

        $rel->save();
    }

    public function deactivate($companyId)
    {
        $rel = $this->invitations()->where('company_id', $companyId)->firstOrFail();
        $rel->is_active = false;

        $rel->save();
    }

    /**
     * Return the company that is selected by the logged user
     *
     * This method verify that the user belongs to selected company (prevents data leak)
     *
     * @return mixed
     */
    public function getActiveCompany()
    {
        return $this->companies()->where('companies.id', get_active_company())->first();
    }

    public function agencyCampaigns()
    {
        return $this->hasMany(Campaign::class, 'agency_id', 'id');
    }

    /**
     * Check if user belongs to given company
     * @param Company $company
     * @return bool
     */
    public function belongsToCompany(Company $company)
    {
        return $this->companies()->where('companies.id', $company->id)->count() === 1;
    }

    /**
     * The roles that belong to the user.
     */
    public function companies()
    {
        return $this->belongsToMany(Company::class)->using(CompanyUser::class)->withPivot('role', 'config', 'completed_at');
    }

    /**
     * The roles that belong to the user.
     */
    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class);
    }

    /**
     * Return the list of users that logged user can see
     * Site Admin can see all users, company user can see company's user
     *
     * @return User[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getListOfUsers($companyId = null)
    {
        if ($this->isAdmin()) {
            return $companyId ? Company::findOrFail($companyId)->users : self::all();
        } else if ($this->isCompanyAdmin(get_active_company())) {
            return Company::findOrFail(get_active_company())->users;
        }
        return [];
    }

    public function getRole(Company $company)
    {
        if ($this->isAdmin()) {
            return 'site_admin';
        } else {
            return $this->invitations()->where('company_id', $company->id)->firstOrFail()->role;
        }
    }

    public function getTimezone(Company $company)
    {
        if ($this->isAdmin()) {
            return null;
        } else {
            return $this->invitations()->where('company_id', $company->id)->firstOrFail()->config['timezone'];
        }
    }

    public function hasPendingInvitations()
    {
        if ($this->isAdmin()) {
            return false;
        } else {
            return $this->invitations()->whereNull('completed_at')->count() > 0;
        }
    }

    public function invitations()
    {
        return $this->hasMany(CompanyUser::class, 'user_id', 'id');
    }

    public function isActive($companyId)
    {
        return $this->invitations()->where('company_id', $companyId)->firstOrFail()->is_active;
    }

    public function isAdmin(): bool
    {
        return (bool)$this->is_admin;
    }

    public function isCompanyAdmin(int $companyId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        $company = $this->companies()->find($companyId);
        return $company && $company->pivot->role == self::ROLE_ADMIN;
    }

    public function isCompanyUser(int $companyId): bool
    {
        $company = $this->companies()->find($companyId);
        if (empty($company) || $company->pivot->role != self::ROLE_USER) {
            return false;
        }
        return true;
    }

    /**
     * Method that verify if user belongs to an agency company (user or admin)
     * @param int|null $companyId Id of company if we want to verify specific company
     * @return bool
     */
    public function isAgencyUser(int $companyId = null): bool
    {
        $userCompanies = $this->companies()
            ->where('companies.type', 'agency');
        if ($companyId) {
            $userCompanies->where('companies.id', $companyId);
        }
        return $userCompanies->count() > 0;
    }


    /**
     * Method that verify if user belongs to an dealership company (user or admin)
     * @param int|null $companyId Id of company if we want to verify specific company
     * @return bool
     */
    public function isDealershipUser(int $companyId = null): bool
    {
        $userCompanies = $this->companies()
            ->where('companies.type', 'dealership');
        if ($companyId) {
            $userCompanies->where('companies.id', $companyId);
        }
        return $userCompanies->count() > 0;
    }

    public function isProfileCompleted()
    {
        return $this->password !== '' && $this->username !== 'username';
    }

    public function isCompanyProfileReady(Company $company)
    {
        return $this->invitations()->where('company_id', $company->id)->whereNotNull('completed_at')->count() > 0;
    }

    public function getCampaignsForCompany(Company $company)
    {
        return $this->campaigns()
            ->where(function ($query) use ($company) {
                $query
                    ->where('campaigns.agency_id', $company->id)
                    ->orWhere('campaigns.dealership_id', $company->id);
            })
            ->get();
    }

    public function hasAccessToCampaign(int $campaignId)
    {
        $company = $this->campaigns()->find($campaignId);
        if (empty($company)) {
            return false;
        }
        return true;
    }

    public function getPossibleTimezones()
    {
        return self::getPossibleTimezonesForUser();
    }

    static function getPossibleTimezonesForUser()
    {
        return [
            'US/Alaska',
            'US/Aleutian',
            'US/Arizona',
            'US/Central',
            'US/East-Indiana',
            'US/Eastern',
            'US/Hawaii',
            'US/Indiana-Starke',
            'US/Michigan',
            'US/Mountain',
            'US/Pacific',
            'US/Pacific-New',
            'US/Samoa',
        ];
    }
}
