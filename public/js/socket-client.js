// Socket.IO Client
class SocketClient {
    constructor() {
        this.socket = null;
        this.isConnected = false;
        this.userId = null;
        this.token = null;
    }

    // Socket bağlantısını başlat
    connect(userId, token) {
        this.userId = userId;
        this.token = token;

        // Socket.IO client'ı yükle
        if (typeof io === 'undefined') {
            this.loadSocketIO(() => {
                this.initializeSocket();
            });
        } else {
            this.initializeSocket();
        }
    }

    // Socket.IO script'ini dinamik olarak yükle
    loadSocketIO(callback) {
        const script = document.createElement('script');
        script.src = 'https://cdn.socket.io/4.7.5/socket.io.min.js';
        script.onload = callback;
        document.head.appendChild(script);
    }

    // Socket bağlantısını başlat
    initializeSocket() {
        this.socket = io('http://localhost:3001', {
            transports: ['websocket', 'polling']
        });

        // Bağlantı olayları
        this.socket.on('connect', () => {
            console.log('Socket.IO bağlantısı kuruldu');
            this.isConnected = true;
            this.authenticate();
        });

        this.socket.on('disconnect', () => {
            console.log('Socket.IO bağlantısı kesildi');
            this.isConnected = false;
        });

        this.socket.on('connect_error', (error) => {
            console.error('Socket.IO bağlantı hatası:', error);
        });

        // Soru güncellemelerini dinle
        this.setupQuestionListeners();
    }

    // Kullanıcı kimlik doğrulaması
    authenticate() {
        if (this.userId && this.token) {
            this.socket.emit('user_login', {
                userId: this.userId,
                token: this.token
            });
        }
    }

    // Soru güncellemelerini dinle
    setupQuestionListeners() {
        // Yeni soru oluşturuldu
        this.socket.on('question_created', (data) => {
            console.log('Yeni soru oluşturuldu:', data);
            this.handleQuestionCreated(data);
        });

        // Soru güncellendi
        this.socket.on('question_updated', (data) => {
            console.log('Soru güncellendi:', data);
            this.handleQuestionUpdated(data);
        });

        // Soru silindi
        this.socket.on('question_deleted', (data) => {
            console.log('Soru silindi:', data);
            this.handleQuestionDeleted(data);
        });

        // Kategori güncellendi
        this.socket.on('category_updated', (data) => {
            console.log('Kategori güncellendi:', data);
            this.handleCategoryUpdated(data);
        });

        // Turnuva güncellendi
        this.socket.on('tournament_updated', (data) => {
            console.log('Turnuva güncellendi:', data);
            this.handleTournamentUpdated(data);
        });
    }

    // Soru listesini anlık getir
    async getQuestions(categoryId = null, search = '', page = 1, perPage = 15) {
        try {
            let url = `http://localhost:3001/api/questions?page=${page}&per_page=${perPage}`;
            
            if (categoryId) {
                url += `&categoryId=${categoryId}`;
            }
            
            if (search) {
                url += `&search=${encodeURIComponent(search)}`;
            }

            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                return data;
            } else {
                throw new Error(data.message || 'Sorular yüklenirken hata oluştu');
            }
        } catch (error) {
            console.error('Soru listesi hatası:', error);
            throw error;
        }
    }

    // Kategori listesini anlık getir
    async getCategories() {
        try {
            const response = await fetch('http://localhost:3001/api/categories');
            const data = await response.json();
            
            if (data.success) {
                return data;
            } else {
                throw new Error(data.message || 'Kategoriler yüklenirken hata oluştu');
            }
        } catch (error) {
            console.error('Kategori listesi hatası:', error);
            throw error;
        }
    }

    // Kategori sorularını dinle
    subscribeToCategoryQuestions(categoryId) {
        if (this.socket) {
            this.socket.emit('subscribe_questions', { categoryId });
        }
    }

    // Turnuva sorularını dinle
    subscribeToTournamentQuestions(tournamentId) {
        if (this.socket) {
            this.socket.emit('subscribe_questions', { tournamentId });
        }
    }

    // Dinlemeyi bırak
    unsubscribeFromQuestions(categoryId = null, tournamentId = null) {
        if (this.socket) {
            this.socket.emit('unsubscribe_questions', { categoryId, tournamentId });
        }
    }

    // Soru oluşturuldu event handler
    handleQuestionCreated(data) {
        // Toastr bildirimi
        if (typeof toastr !== 'undefined') {
            toastr.success('Yeni soru eklendi!', 'BilBakalim');
        }

        // Sayfa yenileme (eğer sorular sayfasındaysa)
        if (window.location.pathname.includes('/admin/questions')) {
            this.refreshQuestionsList();
        }
    }

    // Soru güncellendi event handler
    handleQuestionUpdated(data) {
        if (typeof toastr !== 'undefined') {
            toastr.info('Soru güncellendi!', 'BilBakalim');
        }

        if (window.location.pathname.includes('/admin/questions')) {
            this.refreshQuestionsList();
        }
    }

    // Soru silindi event handler
    handleQuestionDeleted(data) {
        if (typeof toastr !== 'undefined') {
            toastr.warning('Soru silindi!', 'BilBakalim');
        }

        if (window.location.pathname.includes('/admin/questions')) {
            this.refreshQuestionsList();
        }
    }

    // Kategori güncellendi event handler
    handleCategoryUpdated(data) {
        if (typeof toastr !== 'undefined') {
            toastr.info('Kategori güncellendi!', 'BilBakalim');
        }

        if (window.location.pathname.includes('/admin/categories')) {
            this.refreshCategoriesList();
        }
    }

    // Turnuva güncellendi event handler
    handleTournamentUpdated(data) {
        if (typeof toastr !== 'undefined') {
            toastr.info('Turnuva güncellendi!', 'BilBakalim');
        }

        if (window.location.pathname.includes('/admin/tournaments')) {
            this.refreshTournamentsList();
        }
    }

    // Sorular listesini yenile
    refreshQuestionsList() {
        if (typeof $ !== 'undefined' && $('#questions-table').length) {
            // AJAX ile sorular listesini yenile
            $.ajax({
                url: '/admin/questions',
                method: 'GET',
                success: function(response) {
                    // Tabloyu güncelle
                    $('#questions-table tbody').html(response);
                }
            });
        }
    }

    // Kategoriler listesini yenile
    refreshCategoriesList() {
        if (typeof $ !== 'undefined' && $('#categories-table').length) {
            $.ajax({
                url: '/admin/categories',
                method: 'GET',
                success: function(response) {
                    $('#categories-table tbody').html(response);
                }
            });
        }
    }

    // Turnuvalar listesini yenile
    refreshTournamentsList() {
        if (typeof $ !== 'undefined' && $('#tournaments-table').length) {
            $.ajax({
                url: '/admin/tournaments',
                method: 'GET',
                success: function(response) {
                    $('#tournaments-table tbody').html(response);
                }
            });
        }
    }

    // Bağlantıyı kapat
    disconnect() {
        if (this.socket) {
            this.socket.emit('user_logout');
            this.socket.disconnect();
            this.isConnected = false;
        }
    }
}

// Global socket client instance
window.socketClient = new SocketClient();

// Sayfa yüklendiğinde socket'i başlat
$(document).ready(function() {
    // Kullanıcı bilgilerini al
    const userId = $('meta[name="user-id"]').attr('content');
    const token = $('meta[name="api-token"]').attr('content');
    
    if (userId && token) {
        window.socketClient.connect(userId, token);
    }
});
