/**
 * メイン投稿フォームのJavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('uploadForm');
    const fileInput = document.getElementById('image');
    const fileName = document.getElementById('fileName');
    const imagePreview = document.getElementById('imagePreview');
    const submitBtn = document.getElementById('submitBtn');
    const resultMessage = document.getElementById('resultMessage');

    // ファイル選択時の処理
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            fileName.textContent = file.name;
            
            // 画像プレビュー
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.innerHTML = `<img src="${e.target.result}" alt="プレビュー">`;
                imagePreview.classList.add('active');
            };
            reader.readAsDataURL(file);
        } else {
            fileName.textContent = '';
            imagePreview.classList.remove('active');
        }
    });

    // フォーム送信
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        const file = fileInput.files[0];

        if (!file) {
            showMessage('画像を選択してください', 'error');
            return;
        }

        // ボタンを無効化
        submitBtn.disabled = true;
        submitBtn.querySelector('.btn-text').style.display = 'none';
        submitBtn.querySelector('.btn-loading').style.display = 'inline';

        try {
            const response = await fetch('api/upload.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                showMessage('投稿が完了しました！', 'success');
                
                // 結果の詳細を表示
                const results = data.results;
                let details = '<div style="margin-top: 10px; font-size: 12px;">';
                details += `<div>Instagram: ${results.instagram.success ? '✓ 成功' : '✗ 失敗'}</div>`;
                details += `<div>X (Twitter): ${results.twitter.success ? '✓ 成功' : '✗ 失敗'}</div>`;
                details += `<div>Facebook: ${results.facebook.success ? '✓ 成功' : '✗ 失敗'}</div>`;
                details += '</div>';
                
                resultMessage.innerHTML += details;
                
                // フォームをリセット
                form.reset();
                fileName.textContent = '';
                imagePreview.classList.remove('active');
            } else {
                showMessage(data.error || '投稿に失敗しました', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage('通信エラーが発生しました', 'error');
        } finally {
            // ボタンを再有効化
            submitBtn.disabled = false;
            submitBtn.querySelector('.btn-text').style.display = 'inline';
            submitBtn.querySelector('.btn-loading').style.display = 'none';
        }
    });

    function showMessage(message, type) {
        resultMessage.textContent = message;
        resultMessage.className = 'result-message ' + type;
        resultMessage.style.display = 'block';
        
        // 3秒後に自動で非表示（成功時のみ）
        if (type === 'success') {
            setTimeout(() => {
                resultMessage.style.display = 'none';
            }, 5000);
        }
    }
});

