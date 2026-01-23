#!/usr/bin/env python3
"""
Diagnostic script to trace missing physical count data for a specific date.
Usage: python diagnose_missing_data.py 2025-11-23
"""

import os
import json
import sys
from datetime import datetime

# Configuration (matches server.py)
USE_S3 = os.environ.get('USE_S3', os.environ.get('S3_BUCKET', '')) != ''
S3_BUCKET = os.environ.get('S3_BUCKET', '')
S3_REGION = os.environ.get('S3_REGION', 'eu-west-2')
S3_PREFIX = os.environ.get('S3_PREFIX', 'uploads/')
UPLOAD_FOLDER = 'uploads'

def _s3_key(name: str) -> str:
    prefix = S3_PREFIX or ''
    if prefix and not prefix.endswith('/'):
        prefix = prefix + '/'
    return f"{prefix}{name.lstrip('/')}"

def check_s3_file(date):
    """Check if file exists in S3 and show its contents"""
    try:
        import boto3
        s3 = boto3.client('s3', region_name=S3_REGION)
        
        filename = f'physical_state_{date}.json'
        key = _s3_key(filename)
        
        print(f"\n🔍 Checking S3 bucket: {S3_BUCKET}")
        print(f"   Key: {key}")
        
        try:
            # Check if file exists
            s3.head_object(Bucket=S3_BUCKET, Key=key)
            print(f"   ✅ File EXISTS in S3")
            
            # Read the file
            obj = s3.get_object(Bucket=S3_BUCKET, Key=key)
            data = json.loads(obj['Body'].read())
            
            # Analyze the data
            physical_counts = data.get('physical_counts', {})
            notes = data.get('notes', {})
            locations_map = data.get('locations_map', {})
            saved_at = data.get('saved_at', 'Unknown')
            
            print(f"\n📊 File Contents:")
            print(f"   Saved at: {saved_at}")
            print(f"   Physical counts: {len(physical_counts)} cameras")
            print(f"   Notes: {len(notes)} entries")
            print(f"   Locations: {len(locations_map)} entries")
            
            if len(physical_counts) > 0:
                print(f"\n   ✅ FOUND {len(physical_counts)} physical counts!")
                # Show first 5 as sample
                sample = list(physical_counts.items())[:5]
                print(f"   Sample (first 5):")
                for code, checked in sample:
                    print(f"      {code}: {checked}")
            else:
                print(f"\n   ❌ NO physical counts found in file!")
                
            return data
            
        except s3.exceptions.NoSuchKey:
            print(f"   ❌ File NOT FOUND in S3")
            return None
        except Exception as e:
            print(f"   ❌ Error reading from S3: {e}")
            return None
            
    except ImportError:
        print("❌ boto3 not installed, cannot check S3")
        return None
    except Exception as e:
        print(f"❌ Error connecting to S3: {e}")
        return None

def check_local_file(date):
    """Check if file exists locally and show its contents"""
    filename = f'physical_state_{date}.json'
    filepath = os.path.join(UPLOAD_FOLDER, filename)
    
    print(f"\n🔍 Checking local file: {filepath}")
    
    if os.path.exists(filepath):
        print(f"   ✅ File EXISTS locally")
        
        try:
            with open(filepath, 'r') as f:
                data = json.load(f)
            
            # Analyze the data
            physical_counts = data.get('physical_counts', {})
            notes = data.get('notes', {})
            locations_map = data.get('locations_map', {})
            saved_at = data.get('saved_at', 'Unknown')
            
            print(f"\n📊 File Contents:")
            print(f"   Saved at: {saved_at}")
            print(f"   Physical counts: {len(physical_counts)} cameras")
            print(f"   Notes: {len(notes)} entries")
            print(f"   Locations: {len(locations_map)} entries")
            
            if len(physical_counts) > 0:
                print(f"\n   ✅ FOUND {len(physical_counts)} physical counts!")
                # Show first 5 as sample
                sample = list(physical_counts.items())[:5]
                print(f"   Sample (first 5):")
                for code, checked in sample:
                    print(f"      {code}: {checked}")
            else:
                print(f"\n   ❌ NO physical counts found in file!")
                
            return data
            
        except Exception as e:
            print(f"   ❌ Error reading file: {e}")
            return None
    else:
        print(f"   ❌ File NOT FOUND locally")
        return None

def list_all_physical_state_files():
    """List all physical_state files to see what dates exist"""
    print(f"\n📁 Listing all physical_state files:")
    
    if USE_S3:
        try:
            import boto3
            s3 = boto3.client('s3', region_name=S3_REGION)
            
            prefix = _s3_key('')
            resp = s3.list_objects_v2(Bucket=S3_BUCKET, Prefix=prefix)
            
            files = []
            for obj in resp.get('Contents', []):
                key = obj['Key']
                name = key[len(prefix):] if key.startswith(prefix) else key
                if name.startswith('physical_state_') and name.endswith('.json'):
                    files.append((name, obj['LastModified'], obj['Size']))
            
            files.sort(key=lambda x: x[1], reverse=True)
            
            print(f"\n   Found {len(files)} physical_state files in S3:")
            for name, modified, size in files[:20]:  # Show first 20
                print(f"      {name} - {modified} ({size} bytes)")
                
        except Exception as e:
            print(f"   ❌ Error listing S3 files: {e}")
    else:
        try:
            files = []
            for name in os.listdir(UPLOAD_FOLDER):
                if name.startswith('physical_state_') and name.endswith('.json'):
                    filepath = os.path.join(UPLOAD_FOLDER, name)
                    stat = os.stat(filepath)
                    files.append((name, datetime.fromtimestamp(stat.st_mtime), stat.st_size))
            
            files.sort(key=lambda x: x[1], reverse=True)
            
            print(f"\n   Found {len(files)} physical_state files locally:")
            for name, modified, size in files[:20]:  # Show first 20
                print(f"      {name} - {modified} ({size} bytes)")
                
        except Exception as e:
            print(f"   ❌ Error listing local files: {e}")

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print("Usage: python diagnose_missing_data.py YYYY-MM-DD")
        print("Example: python diagnose_missing_data.py 2025-11-23")
        sys.exit(1)
    
    date = sys.argv[1]
    
    print(f"🔍 Diagnosing missing physical count data for date: {date}")
    print(f"   Storage mode: {'S3' if USE_S3 else 'Local'}")
    
    if USE_S3:
        data = check_s3_file(date)
    else:
        data = check_local_file(date)
    
    # List all files to see what's available
    list_all_physical_state_files()
    
    print("\n" + "="*60)
    if data and len(data.get('physical_counts', {})) > 0:
        print("✅ CONCLUSION: Data exists and contains physical counts")
        print("   The issue may be in how the frontend is loading/displaying the data")
    elif data:
        print("⚠️  CONCLUSION: File exists but contains NO physical counts")
        print("   The data may have been overwritten or never saved properly")
    else:
        print("❌ CONCLUSION: File does not exist")
        print("   The data was never saved or has been deleted")

