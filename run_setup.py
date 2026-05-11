#!/usr/bin/env python3
import subprocess
import sys
import os

# Change to app directory
os.chdir(r'C:\free-cmms 0.04')

# Run PHP script
result = subprocess.run(
    [sys.executable, '-m', 'pip', 'show', 'pip'],  # Dummy to verify Python works
    capture_output=False
)

# Now run PHP
print("Executing Equipment Spare Setup...\n")
print("=" * 70)
print()

try:
    result = subprocess.run(
        ['php', 'execute_equipment_setup.php'],
        capture_output=True,
        text=True,
        cwd=r'C:\free-cmms 0.04'
    )
    
    print(result.stdout)
    
    if result.stderr:
        print("STDERR:")
        print(result.stderr)
    
    print()
    print("=" * 70)
    print(f"Process completed with return code: {result.returncode}")
    
except Exception as e:
    print(f"Error: {e}")
    sys.exit(1)
