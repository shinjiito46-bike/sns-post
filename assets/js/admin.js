/**
 * 管理画面のJavaScript
 */

let currentPage = 1;

document.addEventListener('DOMContentLoaded', function() {
    const refreshBtn = document.getElementById('refreshBtn');
    const postsContainer = document.getElementById('postsContainer');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const pagination = document.getElementById('pagination');

    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            loadPosts(currentPage);
        });
    }

    // 初回読み込み
    loadPosts(1);
});

async function loadPosts(page) {
    const postsContainer = document.getElementById('postsContainer');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const pagination = document.getElementById('pagination');

    loadingIndicator.style.display = 'block';
    postsContainer.innerHTML = '';

    try {
        const response = await fetch(`api/posts.php?page=${page}&per_page=20`);
        const data = await response.json();

        if (data.success) {
            displayPosts(data.posts);
            displayPagination(data.pagination);
            currentPage = page;
        } else {
            postsContainer.innerHTML = '<div class="alert alert-error">投稿の読み込みに失敗しました</div>';
        }
    } catch (error) {
        console.error('Error:', error);
        postsContainer.innerHTML = '<div class="alert alert-error">通信エラーが発生しました</div>';
    } finally {
        loadingIndicator.style.display = 'none';
    }
}

function displayPosts(posts) {
    const container = document.getElementById('postsContainer');

    if (posts.length === 0) {
        container.innerHTML = '<div class="alert alert-info">投稿がありません</div>';
        return;
    }

    let html = '';
    posts.forEach(post => {
        const createdDate = new Date(post.created_at).toLocaleString('ja-JP');
        
        html += `
            <div class="post-card" data-post-id="${post.id}">
                <img src="${post.image_url}" alt="投稿画像" class="post-image" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'300\' height=\'200\'%3E%3Crect fill=\'%23ddd\' width=\'300\' height=\'200\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\'%3E画像なし%3C/text%3E%3C/svg%3E'">
                ${post.caption ? `<div class="post-caption">${escapeHtml(post.caption)}</div>` : ''}
                
                <div class="post-status">
                    <span class="status-badge ${post.instagram_status}">
                        Instagram: ${getStatusText(post.instagram_status)}
                    </span>
                    <span class="status-badge ${post.twitter_status}">
                        X: ${getStatusText(post.twitter_status)}
                    </span>
                    <span class="status-badge ${post.facebook_status}">
                        Facebook: ${getStatusText(post.facebook_status)}
                    </span>
                </div>

                ${getStatusDetails(post)}

                <div class="post-actions">
                    <button class="btn btn-danger" onclick="deletePost(${post.id})">削除</button>
                </div>

                <div class="post-meta">
                    投稿日時: ${createdDate}
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function getStatusText(status) {
    const statusMap = {
        'success': '成功',
        'failed': '失敗',
        'pending': '処理中'
    };
    return statusMap[status] || status;
}

function getStatusDetails(post) {
    let details = '<div class="status-detail">';
    
    if (post.instagram_error) {
        details += `<div>Instagramエラー: ${escapeHtml(post.instagram_error)}</div>`;
    }
    if (post.twitter_error) {
        details += `<div>Xエラー: ${escapeHtml(post.twitter_error)}</div>`;
    }
    if (post.facebook_error) {
        details += `<div>Facebookエラー: ${escapeHtml(post.facebook_error)}</div>`;
    }
    
    details += '</div>';
    return details;
}

function displayPagination(pagination) {
    const container = document.getElementById('pagination');
    
    if (pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '';

    // 前へボタン
    html += `<button ${pagination.page === 1 ? 'disabled' : ''} onclick="loadPosts(${pagination.page - 1})">前へ</button>`;

    // ページ番号
    for (let i = 1; i <= pagination.total_pages; i++) {
        if (i === 1 || i === pagination.total_pages || (i >= pagination.page - 2 && i <= pagination.page + 2)) {
            html += `<button class="${i === pagination.page ? 'active' : ''}" onclick="loadPosts(${i})">${i}</button>`;
        } else if (i === pagination.page - 3 || i === pagination.page + 3) {
            html += `<span>...</span>`;
        }
    }

    // 次へボタン
    html += `<button ${pagination.page === pagination.total_pages ? 'disabled' : ''} onclick="loadPosts(${pagination.page + 1})">次へ</button>`;

    container.innerHTML = html;
}

async function deletePost(postId) {
    if (!confirm('この投稿を削除しますか？画像ファイルも削除されます。')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('post_id', postId);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('api/delete.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert('削除が完了しました');
            loadPosts(currentPage);
        } else {
            alert('削除に失敗しました: ' + (data.error || '不明なエラー'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('通信エラーが発生しました');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

