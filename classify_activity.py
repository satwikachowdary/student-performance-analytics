import sys
import json
import re

def classify_activity(activity_text):
    """
    Classifies the intensity of an extracurricular activity based on keywords.
    Returns a dictionary with 'intensity' (1-10) and 'category'.
    """
    text = activity_text.lower()
    
    if not text or text == 'none' or text == 'no':
        return {"intensity": 0, "category": "None"}

    # default
    score = 4
    category = "Moderate"

    # High Intensity Keywords (8-10)
    high_keywords = [
        "varsity", "captain", "president", "daily", "competitive", 
        "athlete", "job", "internship", "research", "lead"
    ]
    
    # Medium Intensity Keywords (5-7)
    medium_keywords = [
        "club", "member", "weekly", "volunteer", "band", "orchestra", 
        "drama", "debate", "team", "choir", "council", "society"
    ]
    
    # Low Intensity Keywords (1-4)
    low_keywords = [
        "hobby", "occasional", "reading", "listening", "watching", 
        "gaming", "monthly", "attending"
    ]

    # Heuristic scoring
    for word in high_keywords:
        if word in text:
            return {"intensity": 9, "category": "High"}
            
    for word in medium_keywords:
        if word in text:
            return {"intensity": 6, "category": "Medium"}
            
    for word in low_keywords:
        if word in text:
            return {"intensity": 3, "category": "Low"}

    # Fallback/Contextual adjustment
    # Length of description might imply more involvement? keeping it simple for now.
    
    return {"intensity": score, "category": category}

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"intensity": 0, "category": "None"}))
    else:
        input_text = sys.argv[1]
        result = classify_activity(input_text)
        print(json.dumps(result))
