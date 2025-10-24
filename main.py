import sys
import json
import warnings
warnings.filterwarnings("ignore", category=UserWarning, module="langchain_core")
from langgraph.graph import StateGraph, START, END
from typing import TypedDict, Annotated
from langchain_core.messages import BaseMessage, HumanMessage
from langgraph.graph.message import add_messages
from langgraph.checkpoint.sqlite import SqliteSaver
from langchain_ollama import ChatOllama
import sqlite3

# Suppress warnings
warnings.filterwarnings("ignore")

# Initialize model
llm = ChatOllama(model="llama3.2-vision", temperature=0.2)

# Define chat state
class chatState(TypedDict):
    messages: Annotated[list[BaseMessage], add_messages]

# Define chat node
def chatnode(state: chatState):
    messages = state["messages"]
    response = llm.invoke(messages)
    return {"messages": [response]}

# Build graph
graph = StateGraph(chatState)
graph.add_node("chat_node", chatnode)
graph.add_edge(START, "chat_node")
graph.add_edge("chat_node", END)

conn = sqlite3.connect(database='chatbot_db',check_same_thread=False)
checkpointer = SqliteSaver(conn=conn)
chatbot = graph.compile(checkpointer=checkpointer)

try:
    # Read JSON input from PHP
    data = sys.stdin.read().strip()
    if not data:
        raise ValueError("No input data received from PHP")

    user_data = json.loads(data)
    user_input = user_data.get("userInput", "").strip()
    thread_id = user_data.get("thread_id", "45")
    
    # Update config with thread_id from PHP
    config = {"configurable": {"thread_id": thread_id}}

    if not user_input:
        raise ValueError("No userInput key found in JSON")

    # Get the response
    result = chatbot.invoke(
        {"messages": [HumanMessage(content=user_input)]},
        config=config
    )
    
    # Extract the AI response
    if "messages" in result and result["messages"]:
        ai_response = result["messages"][-1].content
    else:
        ai_response = "No response generated"

    # Send the response as JSON
    response_data = {
        "response": ai_response,
        "status": "success"
    }
    
    print(json.dumps(response_data))
    sys.stdout.flush()

except Exception as e:
    error_response = {
        "response": f"Error: {str(e)}",
        "status": "error"
    }
    print(json.dumps(error_response))
    sys.stdout.flush()