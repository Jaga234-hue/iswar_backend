import sys
import json
import warnings
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
    message: Annotated[list[BaseMessage], add_messages]

# Define chat node
def chatnode(state: chatState):
    message = state["message"]
    response = llm.invoke(message)
    return {"message": [response]}

# Build graph
graph = StateGraph(chatState)
graph.add_node("chat_node", chatnode)
graph.add_edge(START, "chat_node")
graph.add_edge("chat_node", END)


conn = sqlite3.connect(database='chatbot_db',check_same_thread=False)
# In-memory checkpoint
checkpointer = SqliteSaver(conn=conn)
chatbot = graph.compile(checkpointer=checkpointer)
config = {"configurable": {"thread_id":"45"}}

try:
    # Read JSON input from PHP
    data = sys.stdin.read().strip()
    if not data:
        raise ValueError("No input data received from PHP")

    user_data = json.loads(data)
    user_input = user_data.get("userInput", "").strip()

    if not user_input:
        raise ValueError("No userInput key found in JSON")

    # Run chatbot
    ai_response = chatbot.invoke(
        {"message": [HumanMessage(content=user_input)]},
        config=config
    )

    # Prepare and send JSON response
    response_data = {
        "response": ai_response["message"][-1].content,
        "state": str(chatbot.get_state(config))
    }

    print(json.dumps(response_data))
    sys.stdout.flush()  # Make sure output is sent immediately to PHP

except Exception as e:
    error_response = {
        "response": f"Error: {str(e)}"
    }
    print(json.dumps(error_response))
    sys.stdout.flush()
