from flask import Flask, render_template, request, jsonify, session, redirect, url_for
import pandas as pd
import os
from werkzeug.utils import secure_filename
from werkzeug.security import generate_password_hash, check_password_hash
from datetime import datetime, timedelta
import json

app = Flask(__name__)
app.config['UPLOAD_FOLDER'] = 'uploads'
app.config['MAX_CONTENT_LENGTH'] = 16 * 1024 * 1024  # 16MB max file size

# Session/cookie security
app.config['SESSION_COOKIE_HTTPONLY'] = True
app.config['SESSION_COOKIE_SAMESITE'] = 'Lax'
app.config['SESSION_COOKIE_SECURE'] = bool(os.environ.get('SESSION_COOKIE_SECURE', '0') == '1')
app.config['PERMANENT_SESSION_LIFETIME'] = timedelta(hours=int(os.environ.get('SESSION_LIFETIME_HOURS', '12')))

os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)
# Session and simple auth config
app.secret_key = os.environ.get('SECRET_KEY', 'dev-change-me')
AUTH_USERNAME = os.environ.get('AUTH_USERNAME', 'admin')
AUTH_PASSWORD = os.environ.get('AUTH_PASSWORD', 'admin')


# --- Optional S3 storage config ---
USE_S3 = os.environ.get('USE_S3', os.environ.get('S3_BUCKET', '')) != ''
S3_BUCKET = os.environ.get('S3_BUCKET', '')
S3_REGION = os.environ.get('S3_REGION', 'eu-west-2')
S3_PREFIX = os.environ.get('S3_PREFIX', 'uploads/')  # keep keys under uploads/

_s3_client = None

def _get_s3():
    global _s3_client
    if _s3_client is not None:
        return _s3_client
    if not USE_S3:
        return None
    try:
        import boto3  # type: ignore
        _s3_client = boto3.client('s3', region_name=S3_REGION)
        return _s3_client
    except Exception as e:
        # If S3 requested but boto3 missing or misconfigured, fall back to local with a log
        print('S3 disabled due to error:', e)
        return None

def _s3_key(name: str) -> str:
    # normalize to uploads/<name>
    prefix = S3_PREFIX or ''
    if prefix and not prefix.endswith('/'):
        prefix2 = prefix + '/'
    else:
        prefix2 = prefix
    return f"{prefix2}{name.lstrip('/')}"

def storage_exists(name: str) -> bool:
    if USE_S3 and _get_s3():
        s3 = _get_s3()
        try:
            s3.head_object(Bucket=S3_BUCKET, Key=_s3_key(name))
            return True
        except Exception:
            return False
    # local
    return os.path.exists(os.path.join(app.config['UPLOAD_FOLDER'], name))

def storage_read_json(name: str, default=None):
    if USE_S3 and _get_s3():
        s3 = _get_s3()
        try:
            obj = s3.get_object(Bucket=S3_BUCKET, Key=_s3_key(name))
            data = obj['Body'].read()
            return json.loads(data)
        except Exception:
            return default
    # local
    try:
        with open(os.path.join(app.config['UPLOAD_FOLDER'], name), 'r') as f:
            return json.load(f)
    except Exception:
        return default

def storage_write_json(name: str, data_obj):
    payload = json.dumps(data_obj, indent=2).encode('utf-8')
    if USE_S3 and _get_s3():
        s3 = _get_s3()
        s3.put_object(Bucket=S3_BUCKET, Key=_s3_key(name), Body=payload, ContentType='application/json')
        return True
    # local
    path = os.path.join(app.config['UPLOAD_FOLDER'], name)
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'wb') as f:
        f.write(payload)
    return True

def storage_list(prefix: str = ''):
    # returns list of names (relative to uploads/)
    if USE_S3 and _get_s3():
        s3 = _get_s3()
        names = []
        kw = {'Bucket': S3_BUCKET, 'Prefix': _s3_key(prefix)}
        while True:
            resp = s3.list_objects_v2(**kw)
            for it in resp.get('Contents', []) or []:
                key = it['Key']
                # strip S3_PREFIX
                if S3_PREFIX and key.startswith(S3_PREFIX):
                    names.append(key[len(S3_PREFIX):])
                else:
                    names.append(key)
            if resp.get('IsTruncated'):
                kw['ContinuationToken'] = resp.get('NextContinuationToken')
            else:
                break
        return names
    # local
    base = app.config['UPLOAD_FOLDER']
    try:
        return os.listdir(base)
    except Exception:
        return []

# --- File helpers for CSVs ---
from io import BytesIO

def storage_save_filestorage(name: str, fs):
    """Save an uploaded FileStorage under uploads/ name into S3 or local."""
    if USE_S3 and _get_s3():
        s3 = _get_s3()
        bio = BytesIO()
        fs.save(bio)
        bio.seek(0)
        s3.upload_fileobj(bio, S3_BUCKET, _s3_key(name), ExtraArgs={'ContentType': 'text/csv'})
        return True
    # local
    dest = os.path.join(app.config['UPLOAD_FOLDER'], name)
    os.makedirs(os.path.dirname(dest), exist_ok=True)
    fs.save(dest)
    return True

def storage_download_to_local(name: str) -> str:
    """Ensure a local path exists for the given uploads/ name and return it."""
    local_path = os.path.join(app.config['UPLOAD_FOLDER'], f"_cache_{os.path.basename(name)}")
    if USE_S3 and _get_s3():
        s3 = _get_s3()
        s3.download_file(S3_BUCKET, _s3_key(name), local_path)
    # if local, caller can build its own path
    return local_path

# --- Dates index and caching to speed up /list_count_dates ---
from time import time as _now
DATES_INDEX_FILE = 'dates_index.json'
_DATES_CACHE = {'ts': 0.0, 'value': []}
_DATES_CACHE_TTL = 30.0  # seconds

def _get_dates_index_cached():
    try:
        if _DATES_CACHE['value'] and (_now() - _DATES_CACHE['ts'] < _DATES_CACHE_TTL):
            return list(_DATES_CACHE['value'])
        data = storage_read_json(DATES_INDEX_FILE, default=None)
        arr = []
        if isinstance(data, dict):
            arr = list(data.get('dates') or [])
        elif isinstance(data, list):
            arr = list(data)
        arr = sorted(set([d for d in arr if isinstance(d, str)]))
        _DATES_CACHE['value'] = arr
        _DATES_CACHE['ts'] = _now()
        return list(arr)
    except Exception:
        return []

def _set_dates_index(dates_list):
    try:
        arr = sorted(set([d for d in (dates_list or []) if isinstance(d, str)]))
        storage_write_json(DATES_INDEX_FILE, {'dates': arr})
        _DATES_CACHE['value'] = arr
        _DATES_CACHE['ts'] = _now()
        return arr
    except Exception:
        return dates_list or []

def _add_date_to_index(date_str):
    try:
        if not isinstance(date_str, str):
            return
        if not re.fullmatch(r'\d{4}-\d{2}-\d{2}', date_str):
            return
        arr = _get_dates_index_cached()
        if date_str in arr:
            return
        arr.append(date_str)
        _set_dates_index(arr)
    except Exception:
        pass

def _remove_date_from_index(date_str):
    try:
        if not isinstance(date_str, str):
            return
        arr = _get_dates_index_cached()
        if date_str in arr:
            arr.remove(date_str)
            _set_dates_index(arr)
    except Exception:
        pass



def is_api_request():
    try:
        # Treat JSON endpoints and data-modifying routes as API requests
        api_paths = (
            '/upload', '/generate_stock', '/locations_data',
            '/upload_count_csv', '/load_physical_state', '/save_physical_count', '/list_count_dates'
        )
        if any(request.path.startswith(p) for p in api_paths):
            return True
        return request.accept_mimetypes.best == 'application/json' or request.is_json
    except Exception:
        return False

@app.before_request
def require_login():
    # Allow login route and static files without auth
    if request.endpoint in ('login', 'static'):
        return None
    if session.get('logged_in'):
        return None
    # If unauthenticated: return 401 for API calls, otherwise redirect to login
    if is_api_request():
        return jsonify({'error': 'Authentication required'}), 401
    return redirect(url_for('login', next=request.url))


@app.route('/login', methods=['GET', 'POST'])
def login():
    error = None
    next_url = request.args.get('next') or url_for('index')
    if request.method == 'POST':
        # Prefer next from form on POST
        next_url = request.form.get('next') or next_url
        username = (request.form.get('username') or '').strip()
        password = (request.form.get('password') or '').strip()
        user = get_user(username)
        if user and check_password_hash(user.get('password_hash',''), password):
            session.permanent = True  # Make session persistent across browser redirects
            session['logged_in'] = True
            session['user'] = user.get('username')
            session['role'] = user.get('role','user')
            session['must_change'] = bool(user.get('must_change'))
            return redirect(next_url)
        else:
            error = 'Invalid username or password'
    return render_template('login.html', error=error, next=next_url)

@app.before_request
def enforce_password_change():
    # After login, if the user is flagged to change password, force them to /account
    ep = request.endpoint or ''
    # Allow these endpoints while must_change is set
    allowed = {'account', 'logout', 'static', 'login'}
    if session.get('logged_in') and session.get('must_change') and ep not in allowed:
        return redirect(url_for('account'))


@app.route('/logout')
def logout():
    session.clear()
    return redirect(url_for('login'))
# --- Simple file-backed users store ---
USERS_FILE = 'users.json'  # Now stored in S3 or uploads/ via storage functions

def load_users():
    try:
        data = storage_read_json(USERS_FILE, default=None)
        if data is not None:
            if isinstance(data, dict) and 'users' in data:
                return data['users']
            if isinstance(data, list):
                return data
        # Fallback: seed with env credentials if file missing. Force change on first login.
        return [{ 'username': AUTH_USERNAME, 'password_hash': generate_password_hash(AUTH_PASSWORD, method='pbkdf2:sha256'), 'role': 'admin', 'must_change': True }]
    except Exception:
        return [{ 'username': AUTH_USERNAME, 'password_hash': generate_password_hash(AUTH_PASSWORD, method='pbkdf2:sha256'), 'role': 'admin', 'must_change': True }]


def save_users(users):
    storage_write_json(USERS_FILE, {'users': users})


def get_user(username):
    for u in load_users():
        if u.get('username','').lower() == (username or '').lower():
            return u
    return None

import re
# --- Password policy ---
import re as _re
COMMON_WEAK = {'password','123456','qwerty','letmein','welcome','admin','changeme','facewatch','camera'}

def validate_password(pw: str, username: str = None):
    pw = pw or ''
    if len(pw) < 12:
        return False, 'Password must be at least 12 characters long'
    if username and username.lower() in pw.lower():
        return False, 'Password must not contain your username'
    if pw.lower() in COMMON_WEAK:
        return False, 'Password is too common'
    if not _re.search(r'[a-z]', pw):
        return False, 'Password must include a lowercase letter'
    if not _re.search(r'[A-Z]', pw):
        return False, 'Password must include an uppercase letter'
    if not _re.search(r'[0-9]', pw):
        return False, 'Password must include a digit'
    if not _re.search(r'[^A-Za-z0-9]', pw):
        return False, 'Password must include a special character'
    return True, None


ALLOWED_EXTENSIONS = {'csv'}

def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS
@app.route('/users', methods=['GET', 'POST'])
def users_api():
    # Only admins can manage users
    if not session.get('logged_in') or session.get('role') != 'admin':
        if is_api_request():
            return jsonify({'error': 'Forbidden'}), 403
        return redirect(url_for('login', next=request.url))
    if request.method == 'GET':
        users = load_users()
        # Do not expose password hashes in UI list
        safe = [{'username': u.get('username'), 'role': u.get('role','user')} for u in users]
        # If no users file exists yet and env seeded, write it now
        try:
            if not storage_exists(USERS_FILE):
                save_users(users)
        except Exception:
            pass
        if request.accept_mimetypes.best == 'text/html':
            return render_template('users.html')
        return jsonify({'users': safe})
    # POST: replace full list; password fields are plain text and will be hashed
    payload = request.get_json(force=True) or {}
    incoming = payload.get('users')
    if not isinstance(incoming, list):
        return jsonify({'error': 'users must be a list'}), 400
    new_list = []
    seen = set()
    for item in incoming:
        if not isinstance(item, dict):
            continue
        username = (item.get('username') or '').strip()
        if not username:
            continue
        key = username.lower()
        if key in seen:
            continue
        seen.add(key)
        role = (item.get('role') or 'user').lower()
        role = 'admin' if role == 'admin' else 'user'
        # Allow either password (plain) or password_hash (already hashed)
        pwd_plain = item.get('password')
        pwd_hash = item.get('password_hash')
        if pwd_plain:
            pwd_hash = generate_password_hash(pwd_plain, method='pbkdf2:sha256')
        elif not pwd_hash:
            # Keep existing hash if user exists
            old = get_user(username)
            if old:
                pwd_hash = old.get('password_hash')
        if not pwd_hash:
            # Require a password for new users
            return jsonify({'error': f'User {username} missing password'}), 400
        # Password policy check for new/changed passwords
        if pwd_plain:
            ok, msg = validate_password(pwd_plain, username)
            if not ok:
                return jsonify({'error': f'User {username}: {msg}'}), 400
        new_list.append({'username': username, 'password_hash': pwd_hash, 'role': role})
    save_users(new_list)
    return jsonify({'success': True})

@app.route('/support')
def support_page():
    if not session.get('logged_in'):
        return redirect(url_for('login', next=request.url))
    return render_template('support.html')

@app.route('/past_counts')
def past_counts_page():
    if not session.get('logged_in'):
        return redirect(url_for('login', next=request.url))
    return render_template('past_counts.html')

@app.route('/delete_count', methods=['POST'])
def delete_count():
    if not session.get('logged_in'):
        return jsonify({'error': 'Not authenticated'}), 401

    try:
        data = request.get_json()
        date = data.get('date')

        if not date:
            return jsonify({'error': 'Date is required'}), 400

        # Delete the physical state file for this date
        filename = f"physical_state_{date}.json"

        if USE_S3:
            s3 = _get_s3()
            if s3:
                try:
                    s3.delete_object(Bucket=S3_BUCKET, Key=_s3_key(filename))
                except Exception as e:
                    return jsonify({'error': f'Failed to delete from S3: {str(e)}'}), 500
            else:
                return jsonify({'error': 'S3 not configured'}), 500
        else:
            filepath = os.path.join(app.config['UPLOAD_FOLDER'], filename)
            if os.path.exists(filepath):
                os.remove(filepath)
            else:
                return jsonify({'error': 'Count not found'}), 404

        # Remove the date from the index cache
        _remove_date_from_index(date)

        return jsonify({'success': True, 'message': f'Deleted count for {date}'})

    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/users/manage')
def users_page():
    if not session.get('logged_in') or session.get('role') != 'admin':
        return redirect(url_for('login', next=request.url))
    return render_template('users.html')
@app.route('/account', methods=['GET','POST'])
def account():
    if not session.get('logged_in'):
        return redirect(url_for('login', next=request.url))
    error = None
    ok = None
    must_change = bool(session.get('must_change'))
    if request.method == 'POST':
        cur = (request.form.get('current') or '')
        new = (request.form.get('new') or '')
        confirm = (request.form.get('confirm') or '')
        if not new:
            error = 'New password required'
        elif new != confirm:
            error = 'New password and confirmation do not match'
        else:
            ok_pw, msg_pw = validate_password(new, session.get('user'))
            if not ok_pw:
                error = msg_pw
            else:
                user = get_user(session.get('user'))
                if not user or not check_password_hash(user.get('password_hash',''), cur):
                    error = 'Current password is incorrect'
                else:
                    # Update hash and clear must_change
                    users = load_users()
                    for u in users:
                        if (u.get('username') or '').lower() == (session.get('user') or '').lower():
                            u['password_hash'] = generate_password_hash(new, method='pbkdf2:sha256')
                            u['must_change'] = False
                    save_users(users)
                    session['must_change'] = False
                    ok = 'Password updated.'
                    if must_change:
                        return redirect(url_for('index'))
    return render_template('account.html', user=session.get('user'), must_change=must_change, error=error, ok=ok)



# Robust CSV reader that tries common encodings and delimiter inference

def read_csv_flex(path):
    encodings = ['utf-8', 'utf-8-sig', 'cp1252', 'latin-1']
    seps = [None, ',', '\t', ';']  # None = sniff
    last_err = None
    for enc in encodings:
        for sep in seps:
            try:
                df = pd.read_csv(path, encoding=enc, engine='python', sep=sep)
                # Clean headers early (handle BOM, stray spaces)
                df.columns = df.columns.astype(str).str.replace('\ufeff','', regex=False).str.strip()
                # If single column still, try next separator
                if df.shape[1] == 1 and sep is not None:
                    continue
                return df
            except Exception as e:
                last_err = e
                continue
    raise last_err

@app.route('/')
def index():
    return render_template('index.html')


@app.route('/locations')
def locations_page():
    return render_template('locations.html')

@app.route('/locations_data', methods=['GET', 'POST'])
def locations_data():
    try:
# Add simple Account button to header handled in templates/index.html (already wired)

        if request.method == 'GET':
            # If a global locations file exists, treat it as authoritative
            try:
                data = storage_read_json('locations.json', default=None)
                if isinstance(data, dict) and 'locations' in data:
                    locs = [v.strip() for v in (data.get('locations') or []) if isinstance(v, str) and v.strip()]
                    return jsonify({'locations': sorted(set(locs), key=lambda x: x.lower())})
                if isinstance(data, list):
                    locs = [v.strip() for v in data if isinstance(v, str) and v.strip()]
                    return jsonify({'locations': sorted(set(locs), key=lambda x: x.lower())})
                # Otherwise, aggregate from existing per-date saved states as a seed
                locs_seed = set()
                for name in storage_list(''):
                    if name.startswith('physical_state_') and name.endswith('.json'):
                        try:
                            j = storage_read_json(name, default=None)
                            if isinstance(j, dict):
                                for v in (j.get('locations_list') or []):
                                    if isinstance(v, str) and v.strip():
                                        locs_seed.add(v.strip())
                        except Exception:
                            continue
                return jsonify({'locations': sorted(locs_seed, key=lambda x: x.lower())})
            except Exception:
                return jsonify({'locations': []})
        # POST: save
        payload = request.get_json(force=True) or {}
        locations = payload.get('locations') or []
        if not isinstance(locations, list):
            return jsonify({'error': 'locations must be a list'}), 400
        # normalize, dedupe, sort (case-insensitive)
        norm = []
        for v in locations:
            if not isinstance(v, str):
                continue
            s = v.strip()
            if s:
                norm.append(s)
        uniq = []
        seen = set()
        for v in norm:
            k = v.lower()
            if k in seen:
                continue
            seen.add(k)
            uniq.append(v)
        uniq.sort(key=lambda x: x.lower())
        storage_write_json('locations.json', {'locations': uniq})
        return jsonify({'success': True, 'locations': uniq})



    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/upload_count_csv', methods=['POST'])
def upload_count_csv():
    try:
        # Date to apply updates to
        date = request.args.get('date') or request.form.get('date')
        if not date:
            return jsonify({'error': 'date is required'}), 400
        if 'count_file' not in request.files:
            return jsonify({'error': 'CSV file is required'}), 400
        f = request.files['count_file']
        if f.filename == '':
            return jsonify({'error': 'CSV file must be selected'}), 400
        if not allowed_file(f.filename):
            return jsonify({'error': 'Only CSV files are allowed'}), 400

        # Save temp file into uploads to parse
        temp_name = secure_filename(f.filename)
        temp_path = os.path.join(app.config['UPLOAD_FOLDER'], f"_tmp_{temp_name}")
        f.save(temp_path)
        try:
            df = read_csv_flex(temp_path)
        finally:
            try:
                os.remove(temp_path)
            except Exception:
                pass

        # Column detection
        code_col = find_camera_code_column(df)
        if not code_col:
            return jsonify({'error': 'Camera Code column not found in CSV'}), 400
        # Physical column candidates
        phys_col = None
        note_col = None
        loc_col = None
        for col in df.columns:
            lc = str(col).lower().strip()
            if phys_col is None and ('physical' in lc or 'count' in lc or 'present' in lc or 'checked' in lc):
                phys_col = col
            if note_col is None and 'note' in lc:
                note_col = col
            if loc_col is None and 'location' in lc:
                loc_col = col
        if phys_col is None and note_col is None and loc_col is None:
            return jsonify({'error': 'No Physical/Notes/Location columns found'}), 400

        # Build union of known locations (global + per-date files)
        def union_locations():
            locs = set()
            try:
                data = storage_read_json('locations.json', default=None)
                if isinstance(data, dict):
                    for v in (data.get('locations') or []):
                        if isinstance(v, str) and v.strip():
                            locs.add(v.strip())
                elif isinstance(data, list):
                    for v in data:
                        if isinstance(v, str) and v.strip():
                            locs.add(v.strip())
                for name in storage_list(''):
                    if name.startswith('physical_state_') and name.endswith('.json'):
                        try:
                            j = storage_read_json(name, default=None)
                            if isinstance(j, dict):
                                for v in (j.get('locations_list') or []):
                                    if isinstance(v, str) and v.strip():
                                        locs.add(v.strip())
                        except Exception:
                            continue
            except Exception:
                pass
            return sorted(locs, key=lambda x: x.lower())

        known_locations = set(union_locations())

        def parse_bool(v):
            s = str(v).strip().lower()
            return s in {'1', 'true', 'yes', 'y', 'x', 'checked', 'tick'}

        def norm_cell(val: object) -> str:
            try:
                # Treat None/NaN as empty string
                if val is None:
                    return ''
                if pd.isna(val):
                    return ''
            except Exception:
                pass
            s = str(val).strip()
            if s.lower() == 'nan':
                return ''
            return s

        # Load existing saved state for this date
        name = f'physical_state_{date}.json'
        saved = storage_read_json(name, default={}) or {}
        physical_counts = saved.get('physical_counts', {})
        notes = saved.get('notes', {})
        locations_map = saved.get('locations_map', {})
        locations_list = saved.get('locations_list', [])
        installed_map = saved.get('installed_map', {})

        errors = []
        updated = 0

        # Iterate rows
        for _, row in df.iterrows():
            code = str(row.get(code_col, '')).strip()
            if not code:
                continue
            changed = False
            if phys_col is not None and phys_col in row:
                val = parse_bool(row.get(phys_col))
                if physical_counts.get(code, 0) != (1 if val else 0):
                    physical_counts[code] = 1 if val else 0
                    changed = True
            if note_col is not None and note_col in row:
                v = norm_cell(row.get(note_col))
                if notes.get(code, '') != v:
                    notes[code] = v
                    changed = True
            if loc_col is not None and loc_col in row:
                v = norm_cell(row.get(loc_col))
                if v and v not in known_locations:
                    # Offer to add via query flag add_unknown=1 (client can prompt user)
                    add_unknown = request.args.get('add_unknown') == '1' or request.form.get('add_unknown') == '1'
                    if add_unknown:
                        # Add to global locations and to this date's list
                        try:
                            existing_data = storage_read_json('locations.json', default=[])
                            if isinstance(existing_data, dict):
                                existing = list(existing_data.get('locations') or [])
                            elif isinstance(existing_data, list):
                                existing = list(existing_data)
                            else:
                                existing = []
                            if v not in existing:
                                existing.append(v)
                                existing = sorted(set(existing), key=lambda x: x.lower())
                                storage_write_json('locations.json', {'locations': existing})
                            known_locations.add(v)
                            if v not in locations_list:
                                locations_list.append(v)
                        except Exception:
                            # If we fail to add globally, still report warning and blank out
                            errors.append(f"Unknown location '{v}' for {code}; left blank (failed to add globally)")
                            v = ''
                    else:
                        errors.append(f"Unknown location '{v}' for {code}; left blank")
                        v = ''
                if locations_map.get(code, '') != v:
                    locations_map[code] = v
                    changed = True
                if v and v not in locations_list:
                    locations_list.append(v)
            if changed:
                updated += 1

        # Persist merged state using same semantics as /save_physical_count
        payload = {
            'physical_counts': physical_counts,
            'notes': notes,
            'locations_map': locations_map,
            'locations_list': locations_list,
            'installed_map': installed_map,
            'saved_at': datetime.now().isoformat(),
            'date': date,
        }
        ts = datetime.now().strftime('%Y%m%d_%H%M%S')
        backup_name = f'physical_state_{ts}.json'
        storage_write_json(backup_name, payload)
        storage_write_json(f'physical_state_{date}.json', payload)
        storage_write_json('physical_state.json', payload)

        return jsonify({'success': True, 'updated': updated, 'errors': errors})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/upload', methods=['POST'])
def upload_files():
    try:
        if 'delivered_file' not in request.files or 'installed_file' not in request.files:
            return jsonify({'error': 'Both files are required'}), 400

        delivered_file = request.files['delivered_file']
        installed_file = request.files['installed_file']

        if delivered_file.filename == '' or installed_file.filename == '':
            return jsonify({'error': 'Both files must be selected'}), 400

        if not (allowed_file(delivered_file.filename) and allowed_file(installed_file.filename)):
            return jsonify({'error': 'Only CSV files are allowed'}), 400

        delivered_filename = secure_filename(delivered_file.filename)
        installed_filename = secure_filename(installed_file.filename)

        # Save to S3 or local uploads/
        storage_save_filestorage(delivered_filename, delivered_file)
        storage_save_filestorage(installed_filename, installed_file)

        # Persist last uploaded filenames for future sessions (used to auto-generate after refresh)
        try:
            last_upload = {
                'delivered_file': delivered_filename,
                'installed_file': installed_filename,
                'saved_at': datetime.now().isoformat()
            }
            storage_write_json('last_upload.json', last_upload)
        except Exception:
            pass

        return jsonify({
            'success': True,
            'delivered_file': delivered_filename,
            'installed_file': installed_filename
        })

    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/generate_stock', methods=['POST'])
def generate_stock():
    try:
        data = request.get_json(force=True)
        delivered_file = data.get('delivered_file')
        installed_file = data.get('installed_file')

        if not delivered_file or not installed_file:
            return jsonify({'error': 'File names are required'}), 400

        if USE_S3 and _get_s3():
            delivered_local = storage_download_to_local(delivered_file)
            installed_local = storage_download_to_local(installed_file)
        else:
            delivered_local = os.path.join(app.config['UPLOAD_FOLDER'], delivered_file)
            installed_local = os.path.join(app.config['UPLOAD_FOLDER'], installed_file)

        delivered_df = read_csv_flex(delivered_local)
        installed_df = read_csv_flex(installed_local)

        result = process_stock_data(delivered_df, installed_df)
        return jsonify(result)

    except Exception as e:
        return jsonify({'error': str(e)}), 500

def process_stock_data(delivered_df: pd.DataFrame, installed_df: pd.DataFrame):
    delivered_df = delivered_df.copy()
    installed_df = installed_df.copy()

    delivered_df.columns = delivered_df.columns.str.strip()
    installed_df.columns = installed_df.columns.str.strip()

    installed_filtered = filter_installed_cameras(installed_df)

    camera_code_delivered = find_camera_code_column(delivered_df)
    camera_code_installed = find_camera_code_column(installed_filtered)

    if not camera_code_delivered or not camera_code_installed:
        return {'error': 'Camera Code column not found in one or both files'}

    # Counts per code
    # Normalize codes to lowercase for case-insensitive matching
    delivered_df[camera_code_delivered] = delivered_df[camera_code_delivered].astype(str).str.strip().str.lower()
    installed_filtered[camera_code_installed] = installed_filtered[camera_code_installed].astype(str).str.strip().str.lower()
    delivered_counts = delivered_df[camera_code_delivered].value_counts().to_dict()
    installed_counts = installed_filtered[camera_code_installed].value_counts().to_dict()

    # Delivery date per code (most recent by default)
    delivery_date_col = find_date_column(delivered_df)
    delivery_dates = {}
    if delivery_date_col:
        df_dates = delivered_df[[camera_code_delivered, delivery_date_col]].copy()
        df_dates[camera_code_delivered] = df_dates[camera_code_delivered].astype(str).str.strip()
        raw = df_dates[delivery_date_col].astype(str).str.strip()
        parsed = pd.to_datetime(raw, errors='coerce', dayfirst=True, infer_datetime_format=True)
        alt = pd.to_datetime(raw, format='%b-%y', errors='coerce')
        parsed = parsed.fillna(alt)
        alt2 = pd.to_datetime(raw, format='%b-%Y', errors='coerce')
        parsed = parsed.fillna(alt2)
        parsed = parsed.dt.to_period('M').dt.to_timestamp(how='start')
        df_dates[delivery_date_col] = parsed
        grp = df_dates.dropna(subset=[delivery_date_col]).groupby(camera_code_delivered)[delivery_date_col]
        agg = grp.max()
        delivery_dates = {k: v.strftime('%b-%Y') for k, v in agg.to_dict().items()}

    # Cam Type per code (from delivered file)
    cam_type_col = find_cam_type_column(delivered_df)
    cam_types = {}
    if cam_type_col:
        df_types = delivered_df[[camera_code_delivered, cam_type_col]].copy()
        df_types[camera_code_delivered] = df_types[camera_code_delivered].astype(str).str.strip()
        df_types[cam_type_col] = df_types[cam_type_col].astype(str).str.strip()
        # if multiple, pick most frequent type per code
        cam_types = df_types.groupby(camera_code_delivered)[cam_type_col].agg(lambda s: s.mode().iat[0] if not s.mode().empty else s.iloc[0]).to_dict()

    # Purchase Order per code (from delivered file)
    po_col = find_po_column(delivered_df)
    po_map = {}
    if po_col:
        df_po = delivered_df[[camera_code_delivered, po_col]].copy()
        df_po[camera_code_delivered] = df_po[camera_code_delivered].astype(str).str.strip()
        df_po[po_col] = df_po[po_col].astype(str).str.strip()
        po_map = df_po.groupby(camera_code_delivered)[po_col].agg(lambda s: s.mode().iat[0] if not s.mode().empty else s.iloc[0]).to_dict()

    all_codes = set(delivered_counts.keys()) | set(installed_counts.keys())

    theoretical_stock = []
    anomalies = []

    for code in sorted(all_codes):
        d = delivered_counts.get(code, 0)
        i = installed_counts.get(code, 0)
        t = d - i
        po_value = po_map.get(code)

        # Set default location for PO-0188 cameras
        default_location = None
        if po_value and str(po_value).strip().replace('-', '').replace(' ', '').upper() in ['PO0188', 'PO-0188']:
            default_location = 'Aug 25 PO-0188'

        theoretical_stock.append({
            'camera_code': code,
            'cam_type': cam_types.get(code),
            'delivery_date': delivery_dates.get(code),
            'installed': int(i),
            'po': po_value,
            'theoretical_stock': int(t),
            'default_location': default_location
        })
        if d == 0 and i > 0:
            anomalies.append({
                'type': 'Installed but not delivered',
                'camera_code': code,
                'installed_qty': int(i)
            })
        # Flag multiple deliveries for the same camera code (should not happen)
        if d > 1:
            anomalies.append({
                'type': 'Multiple deliveries',
                'camera_code': code,
                # Reuse the existing column used by the UI for quantity
                'installed_qty': int(d)
            })

    summary = {
        'total_delivered': int(sum(delivered_counts.values())),
        'total_installed': int(sum(installed_counts.values())),
        'total_theoretical_stock': int(sum(max(0, x['theoretical_stock']) for x in theoretical_stock))
    }

    return {'theoretical_stock': theoretical_stock, 'anomalies': anomalies, 'summary': summary}

def filter_installed_cameras(df: pd.DataFrame) -> pd.DataFrame:
    # Flexible matching for columns
    type_col = None
    feed_col = None
    for col in df.columns:
        lc = col.lower().strip()
        if type_col is None and 'type' in lc:
            type_col = col
        if feed_col is None and 'feed' in lc and 'name' in lc:
            feed_col = col
    if type_col is None:
        # Fallback: any column that equals 'SAFR-Camera' in some rows
        for col in df.columns:
            try:
                if (df[col] == 'SAFR-Camera').any():
                    type_col = col
                    break
            except Exception:
                pass
    if feed_col is None:
        # Fallback: any column with name containing 'feed'
        for col in df.columns:
            if 'feed' in col.lower():
                feed_col = col
                break

    if type_col is None or feed_col is None:
        return df.copy()

    filtered = df[
        (df[type_col] == 'SAFR-Camera') &
        (df[feed_col].astype(str).str.strip() != '') &
        (df[feed_col].notna())
    ].copy()
    return filtered

def find_camera_code_column(df: pd.DataFrame):
    candidates = ['Camera Code', 'camera_code', 'CameraCode', 'Code', 'camera code', 'Camera code', 'Camera Codes']
    for col in df.columns:
        if col in candidates:
            return col
    for col in df.columns:
        lc = col.lower()
        if 'camera' in lc and 'code' in lc:
            return col
    return None

# Heuristic delivery date column finder

def find_date_column(df: pd.DataFrame):
    preferred = ['Delivery Date', 'Delivered Date', 'Date Delivered', 'delivery_date']
    for col in df.columns:
        if col in preferred:
            return col
    for col in df.columns:
        if 'date' in col.lower():
            return col
    return None

# Heuristic cam type column finder

def find_cam_type_column(df: pd.DataFrame):
    preferred = ['Cam Type', 'Camera Type', 'cam_type', 'CameraType', 'Type']
    for col in df.columns:
        if col in preferred:
            return col
    for col in df.columns:
        lc = col.lower()
        if 'type' in lc:
            return col
    return None

# Heuristic purchase order column finder

def find_po_column(df: pd.DataFrame):
    preferred = ['PO Ref', 'PO', 'Po', 'po', 'Purchase Order', 'PurchaseOrder', 'purchase_order', 'PO Number', 'Po Number']
    for col in df.columns:
        if col in preferred:
            return col
    for col in df.columns:
        lc = col.lower()
        if 'po ref' in lc or ('purchase' in lc and 'order' in lc) or lc == 'po' or 'po number' in lc or 'po_no' in lc:
            return col
    return None





@app.route('/load_physical_state', methods=['GET'])
def load_physical_state():
    try:
        # Support date-specific loads: /load_physical_state?date=YYYY-MM-DD
        date = request.args.get('date')
        # Helper: read last uploaded filenames if available
        def last_upload_info():
            info = {}
            try:
                j = storage_read_json('last_upload.json', default=None)
                if isinstance(j, dict):
                    info['last_delivered_file'] = j.get('delivered_file')
                    info['last_installed_file'] = j.get('installed_file')
            except Exception:
                pass
            return info

        if date:
            name = f'physical_state_{date}.json'
            if storage_exists(name):
                data = storage_read_json(name, default={}) or {}
                data.update(last_upload_info())
                return jsonify(data)
            else:
                base = {'physical_counts': {}, 'notes': {}, 'locations_map': {}, 'locations_list': []}
                base.update(last_upload_info())
                return jsonify(base)
        # Legacy behavior: stable file
        if not storage_exists('physical_state.json'):
            base = {'physical_counts': {}, 'notes': {}, 'locations_map': {}, 'locations_list': []}
            base.update(last_upload_info())
            return jsonify(base)
        data = storage_read_json('physical_state.json', default={}) or {}
        data.update(last_upload_info())
        return jsonify(data)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/save_physical_count', methods=['POST'])
def save_physical_count():
    try:
        data = request.get_json(force=True)
        counts = data.get('physical_counts', {})
        notes = data.get('notes', {})
        locations_map = data.get('locations_map', {})
        locations_list = data.get('locations_list', [])
        installed_map = data.get('installed_map', {})
        date = data.get('date')  # optional
        theoretical_stock = data.get('theoretical_stock', [])
        payload = {
            'physical_counts': counts,
            'notes': notes,
            'locations_map': locations_map,
            'locations_list': locations_list,
            'installed_map': installed_map,
            'theoretical_stock': theoretical_stock,
            'saved_at': datetime.now().isoformat(),
            'date': date,
        }
        # Write a timestamped backup and a stable file for reloads
        ts = datetime.now().strftime('%Y%m%d_%H%M%S')
        backup_name = f'physical_state_{ts}.json'
        storage_write_json(backup_name, payload)
        # If a date is provided, also write a dated file
        if date:
            dated_name = f'physical_state_{date}.json'
            storage_write_json(dated_name, payload)
            # Update stable pointer to latest saved
            storage_write_json('physical_state.json', payload)
            try:
                _add_date_to_index(date)
            except Exception:
                pass
        else:
            # No date provided: keep stable behavior
            storage_write_json('physical_state.json', payload)
        return jsonify({'success': True, 'filename': backup_name})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/list_count_dates', methods=['GET'])
def list_count_dates():
    try:
        # Use cached index first
        cached = _get_dates_index_cached()
        if cached:
            return jsonify({'dates': cached})
        # Build minimal list without fetching each backup file
        files = storage_list('physical_state_')
        dates = set()
        for name in files:
            if name.startswith('physical_state_') and name.endswith('.json'):
                mid = name[len('physical_state_'):-len('.json')]
                if re.fullmatch(r'\d{4}-\d{2}-\d{2}', mid):
                    dates.add(mid)
        # Include stable file date if present
        try:
            j = storage_read_json('physical_state.json', default=None)
            d = (j or {}).get('date') if isinstance(j, dict) else None
            if isinstance(d, str) and re.fullmatch(r'\d{4}-\d{2}-\d{2}', d):
                dates.add(d)
        except Exception:
            pass
        # Persist index for fast subsequent calls
        dates_list = sorted(dates)
        _set_dates_index(dates_list)
        return jsonify({'dates': dates_list})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    print("Starting Camera Stock Control server...")
    print("Open http://localhost:5001 in your browser")
    try:
        app.run(debug=True, port=5000, host='localhost')
    except Exception as e:
        print(f"Error starting server: {e}")
        print("Trying port 8000...")
        app.run(debug=True, port=8000, host='localhost')

