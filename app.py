from flask import Flask, request, jsonify
from flask_cors import CORS
import requests
import re

app = Flask(__name__)
CORS(app)

# Session مشتركة تحافظ على الكوكيز
session = requests.Session()
session.headers.update({
    'User-Agent': 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
    'Accept-Language': 'ar-IQ,ar;q=0.9,en-US;q=0.8,en;q=0.7',
})

def get_csrf_token():
    """يجيب CSRF token حقيقي من إنستغرام"""
    try:
        r = session.get('https://www.instagram.com/accounts/signup/', timeout=10)
        if 'csrftoken' in session.cookies:
            return session.cookies['csrftoken']
        match = re.search(r'"csrf_token":"([^"]+)"', r.text)
        if match:
            return match.group(1)
    except Exception as e:
        print(f"CSRF error: {e}")
    return None

# جيب CSRF token عند بدء السيرفر
csrf_token = get_csrf_token()
print(f"[✓] CSRF Token: {csrf_token}")

@app.route('/check', methods=['POST'])
def check_username():
    global csrf_token

    data = request.get_json()
    username = data.get('username', '').strip()

    if not username:
        return jsonify({'error': 'No username provided'}), 400

    if not csrf_token:
        csrf_token = get_csrf_token()

    url = 'https://www.instagram.com/api/v1/users/check_username/'

    headers = {
        'Content-Type': 'application/x-www-form-urlencoded',
        'x-csrftoken': csrf_token or '',
        'x-ig-www-claim': '0',
        'x-requested-with': 'XMLHttpRequest',
        'x-ig-app-id': '1217981644879628',
        'x-instagram-ajax': '1015171929',
        'x-asbd-id': '129477',
        'origin': 'https://www.instagram.com',
        'referer': 'https://www.instagram.com/accounts/signup/username/?hl=ar',
        'sec-fetch-site': 'same-origin',
        'sec-fetch-mode': 'cors',
        'sec-fetch-dest': 'empty',
    }

    try:
        response = session.post(
            url,
            params={'hl': 'ar'},
            data=f'username={username}',
            headers=headers,
            timeout=10
        )

        text = response.text
        print(f"[{response.status_code}] @{username} → {text[:150]}")

        if response.status_code == 200:
            if '"available": true' in text or '"available":true' in text:
                return jsonify({'available': True, 'username': username})
            elif '"available": false' in text or '"available":false' in text:
                return jsonify({'available': False, 'username': username})
            else:
                csrf_token = get_csrf_token()
                return jsonify({'available': False, 'username': username, 'note': 'unclear'})

        elif response.status_code in [401, 403]:
            csrf_token = get_csrf_token()
            return jsonify({'available': False, 'username': username, 'note': 'session refreshed'})

        else:
            return jsonify({'available': False, 'username': username, 'note': f'status {response.status_code}'})

    except requests.Timeout:
        return jsonify({'error': 'timeout'}), 504
    except Exception as e:
        print(f"Error: {e}")
        return jsonify({'error': str(e)}), 500


@app.route('/')
def home():
    return f'IG Checker API is running ✅ | CSRF: {"Ready" if csrf_token else "Missing"}'


@app.route('/refresh')
def refresh_session():
    """تجديد الجلسة يدوياً"""
    global csrf_token
    csrf_token = get_csrf_token()
    return jsonify({'status': 'refreshed', 'csrf': csrf_token is not None})


@app.route('/debug')
def debug():
    test_user = 'zzzzzzzzzzzzzz123456789'
    headers = {
        'Content-Type': 'application/x-www-form-urlencoded',
        'x-csrftoken': csrf_token or '',
        'x-ig-www-claim': '0',
        'x-requested-with': 'XMLHttpRequest',
        'x-ig-app-id': '1217981644879628',
        'x-instagram-ajax': '1015171929',
        'origin': 'https://www.instagram.com',
        'referer': 'https://www.instagram.com/accounts/signup/username/?hl=ar',
    }
    try:
        r = session.post(
            'https://www.instagram.com/api/v1/users/check_username/',
            params={'hl': 'ar'},
            data=f'username={test_user}',
            headers=headers,
            timeout=10
        )
        return jsonify({
            'status_code': r.status_code,
            'response': r.text[:500],
            'csrf': csrf_token
        })
    except Exception as e:
        return jsonify({'error': str(e)})
if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
