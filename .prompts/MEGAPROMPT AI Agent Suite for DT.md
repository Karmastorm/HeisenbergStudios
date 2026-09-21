# ⭐ **MEGAPROMPT: AI Agent Suite for Momentum Day Trading Analytics**

You are an expert system architect, quantitative developer, and AI agent designer.  
Your task is to design a complete multi‑agent AI system capable of:

- Reading **real-time market data** (Level 1 + Level 2)
- Performing **real-time market scanning**
- Rendering **charts** and technical indicators
- Detecting **momentum trading principles** described by Ross Cameron in the transcript:
  - High relative volume (≥5× 50‑day average)
  - High total volume
  - % gain ≥ 10–20%
  - Low float (≤20M)
  - Gapping ≥ 2%
  - Hot sector alignment (AI, biotech, crypto, China tech)
  - First pullback pattern
  - First candle to make a new high
  - Risk management: 2:1 profit/loss ratio
  - Exit indicators (topping tail, loss of Level 2 support, break of pullback low)
  - Time‑of‑day performance windows (7–10 AM)
  - Walk‑away rules (loss of half daily gains, max loss, window closed)
  - Post‑trade metrics tracking

Your output must include:

---

## **1. System Overview**
Design a **suite of cooperating AI agents**, each with a clear role:

- **Market Data Agent**  
  Ingests real-time Level 1 + Level 2 data, normalizes feeds, maintains order book state.

- **Scanner Agent**  
  Continuously scans the market for stocks meeting the 5 pillars:
  - Relative volume ≥ 5×  
  - Total volume threshold  
  - % gain threshold  
  - Float threshold  
  - Catalyst detection (news sentiment)

- **Chart Pattern Agent**  
  Detects:
  - First pullback pattern  
  - First candle to make a new high  
  - ABCD pattern  
  - Cup & handle  
  - Trend shifts  
  - Volume profile changes  

- **Level 2 Sentiment Agent**  
  Reads:
  - Bid/ask pressure  
  - Large hidden orders  
  - Tape speed  
  - Imbalance  
  - Spoofing patterns  
  - Momentum confirmation  

- **Risk & Exit Agent**  
  Implements:
  - Max loss  
  - Half‑day giveback rule  
  - Exit indicators  
  - Time‑of‑day performance windows  

- **Trade Tracking Agent**  
  Logs:
  - Entry  
  - Exit  
  - R/R  
  - Time of day  
  - Pattern type  
  - Mistakes (chasing, late entry, extended entry)  
  - Post‑mortem analytics  

- **Visualization Agent**  
  Generates:
  - Candlestick charts  
  - Volume profile  
  - Level 2 heatmaps  
  - Scanner dashboards  
  - Trade replay charts  

- **Strategy Explanation Agent**  
  Highlights **why** a stock meets Ross Cameron’s criteria using transcript‑based rules.

---

## **2. Data Sources (Python‑Friendly APIs)**

Provide examples of real-time market data sources:

### **Level 1 + Level 2 Data**
- **Polygon.io** (full order book, trades, quotes)
- **Tradier API**
- **Alpaca Markets** (real-time equities feed)
- **Interactive Brokers TWS API**
- **dxFeed** (professional-grade Level 2)
- **NASDAQ Basic + TotalView** (via vendors)

### **News & Catalyst Data**
- **Benzinga News API**
- **NewsAPI.org**
- **Finnhub News + Sentiment**
- **AlphaVantage News Sentiment**

### **Fundamental Data (Float, Shares Outstanding)**
- **Polygon Reference API**
- **Finnhub Fundamentals**
- **Tiingo Fundamentals**

### **Charting Libraries**
- **Plotly**
- **Bokeh**
- **Matplotlib**
- **TradingView Lightweight Charts (via Python bindings)**

---

## **3. Architecture Requirements**
Produce:

- Full system architecture diagram  
- Agent communication protocol  
- Event-driven pipeline  
- Real-time ingestion loop  
- Async Python architecture (asyncio + websockets)  
- Database schema for trade logs (PostgreSQL or SQLite)  
- Modular code layout  
- Microservice or monolithic options  

---

## **4. Python Implementation Outline**
Generate:

- Python package structure  
- Class definitions for each agent  
- Real-time data ingestion code  
- Scanner logic (relative volume, float, % gain)  
- Chart pattern detection algorithms  
- Level 2 sentiment analysis  
- Risk management engine  
- Trade logging system  
- Visualization dashboards  
- Backtesting module  
- Replay engine for post‑mortem analysis  

---

## **5. Momentum Strategy Logic (Based on Transcript)**  
Use the transcript to encode rules such as:

> “Five times relative volume is one of my first pillars of stock selection.”  
> “The first candle to make a new high is the entry.”  
> “My max loss is equal to my average daily gain.”  
> “I walk away if I lose half of my day.”

Translate these into:

- Rule-based filters  
- Pattern recognition logic  
- Risk thresholds  
- Behavioral constraints  
- Time-of-day filters  
- Walk-away logic  

---

## **6. Output Requirements**
Your output must include:

- A complete outline for building the software  
- A detailed multi-agent architecture  
- Python pseudocode + real code examples  
- Data pipeline diagrams  
- Explanations of how each agent uses transcript principles  
- Example workflows (market open, trade detection, exit, logging)  
- Suggestions for scaling to production  
- Optional: reinforcement learning extensions  

---

## **7. Deliverables**
Produce:

- **Full architecture document**  
- **Agent definitions**  
- **Python code outline**  
- **Data source integration plan**  
- **Scanner logic**  
- **Chart pattern detection logic**  
- **Level 2 sentiment logic**  
- **Risk management logic**  
- **Trade tracking schema**  
- **Post‑mortem analytics framework**  

---

# ✔️ END OF MEGAPROMPT

---

If you'd like, I can now generate:

- The **full architecture document**  
- The **Python package layout**  
- The **agent code templates**  
- The **scanner logic**  
- The **chart pattern detection algorithms**  
- The **Level 2 sentiment engine**  
- The **trade tracking database schema**  

Just tell me what you want next.