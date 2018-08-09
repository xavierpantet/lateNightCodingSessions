import Cocoa

extension URL {
    func withQueries(_ queries: [String: String]) -> URL? {
        var components = URLComponents(url: self,
                                       resolvingAgainstBaseURL: true)
        components?.queryItems = queries.flatMap
            { URLQueryItem(name: $0.0, value: $0.1) }
        return components?.url
    }
}

let nc = DistributedNotificationCenter.default()
nc.addObserver(forName: nil, object: nil, queue: nil) { notification in
    if let info = notification.userInfo, notification.name.rawValue == "com.apple.iTunes.playerInfo" {
        if let state = info["Player State"] as? String, state == "Playing" {
            if let artist = info["Artist"] as? String, let title = info["Display Line 0"] as? String {
                let baseUrl = URL(string: "https://myserver.com")!
                let query = ["song" : artist + " - " + title,
                             "code" : "PXxr7E4I9KxQhq19GoyhfptUXTvYrqNo"]
                
                let url = baseUrl.withQueries(query)!
                let task = URLSession.shared.dataTask(with: url) {data, response, error in
                    if let data = data, let rawJSON = try? JSONSerialization.jsonObject(with: data), let json = rawJSON as? [String: String], json["status"] == "OK" {
                    }
                }
                task.resume()
            }
        }
    }
}

RunLoop.main.run()
