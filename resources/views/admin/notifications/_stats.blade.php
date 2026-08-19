<div class="row g-3 notif-stat-row mb-3">
    <div class="col-6 col-lg-3">
        <button type="button" class="notif-stat-btn w-100 border-0 bg-transparent p-0 text-start" data-stat-type="">
            <div class="card notif-stat {{ empty(request('type')) ? 'is-active' : '' }}">
                <div class="card-body">
                    <div class="label">Toplam bildirim</div>
                    <div class="value">{{ number_format($stats['total']) }}</div>
                </div>
            </div>
        </button>
    </div>
    <div class="col-6 col-lg-3">
        <button type="button" class="notif-stat-btn w-100 border-0 bg-transparent p-0 text-start" data-stat-type="fcm">
            <div class="card notif-stat {{ request('type') === 'fcm' ? 'is-active' : '' }}">
                <div class="card-body">
                    <div class="label">Push (FCM)</div>
                    <div class="value">{{ number_format($stats['fcm']) }}</div>
                </div>
            </div>
        </button>
    </div>
    <div class="col-6 col-lg-3">
        <button type="button" class="notif-stat-btn w-100 border-0 bg-transparent p-0 text-start" data-stat-type="sms">
            <div class="card notif-stat {{ request('type') === 'sms' ? 'is-active' : '' }}">
                <div class="card-body">
                    <div class="label">SMS</div>
                    <div class="value">{{ number_format($stats['sms']) }}</div>
                </div>
            </div>
        </button>
    </div>
    <div class="col-6 col-lg-3">
        <button type="button" class="notif-stat-btn w-100 border-0 bg-transparent p-0 text-start" data-stat-type="email">
            <div class="card notif-stat {{ request('type') === 'email' ? 'is-active' : '' }}">
                <div class="card-body">
                    <div class="label">E-posta</div>
                    <div class="value">{{ number_format($stats['email']) }}</div>
                </div>
            </div>
        </button>
    </div>
</div>
